<?php

declare(strict_types=1);

namespace ExoSuiteCodingStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use RuntimeException;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use SlevomatCodingStandard\Helpers\FunctionHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use function array_key_exists;
use function array_merge;
use function in_array;
use function is_bool;
use function sprintf;
use function str_repeat;
use function strtolower;
use const T_ABSTRACT;
use const T_CLOSE_CURLY_BRACKET;
use const T_COMMENT;
use const T_CONST;
use const T_DOC_COMMENT;
use const T_DOC_COMMENT_WHITESPACE;
use const T_FINAL;
use const T_FUNCTION;
use const T_OPEN_CURLY_BRACKET;
use const T_PRIVATE;
use const T_PROTECTED;
use const T_PUBLIC;
use const T_SEMICOLON;
use const T_STATIC;
use const T_STRING;
use const T_USE;
use const T_VAR;
use const T_VARIABLE;
use const T_WHITESPACE;

/**
 * This sniff ensures that the class/interface/trait has consistent order of its members.
 * You can adjust required order via $requiredOrder property. Set the same values to ignore certain stages order.
 */
class ClassStructureSniff implements Sniff
{
    public const CODE_INVALID_MEMBER_PLACEMENT = 'InvalidMemberPlacement';

    public const STAGE_NONE = 0;
    public const STAGE_USES = 10;
    public const STAGE_PUBLIC_CONSTANTS = 20;
    public const STAGE_PROTECTED_CONSTANTS = 30;
    public const STAGE_PRIVATE_CONSTANTS = 40;
    public const STAGE_PUBLIC_STATIC_PROPERTIES = 50;
    public const STAGE_PROTECTED_STATIC_PROPERTIES = 60;
    public const STAGE_PRIVATE_STATIC_PROPERTIES = 70;
    public const STAGE_PUBLIC_PROPERTIES = 80;
    public const STAGE_PROTECTED_PROPERTIES = 90;
    public const STAGE_PRIVATE_PROPERTIES = 100;
    public const STAGE_PUBLIC_STATIC_METHODS = 110;
    public const STAGE_PROTECTED_STATIC_METHODS = 120;
    public const STAGE_PRIVATE_STATIC_METHODS = 130;
    public const STAGE_CONSTRUCTOR = 140;
    public const STAGE_STATIC_CONSTRUCTORS = 150;
    public const STAGE_DESTRUCTOR = 160;
    public const STAGE_MAGIC_METHODS = 170;
    public const STAGE_PUBLIC_METHODS = 180;
    public const STAGE_PROTECTED_METHODS = 190;
    public const STAGE_PRIVATE_METHODS = 200;

    private const SPECIAL_METHODS = [
        '__construct' => self::STAGE_CONSTRUCTOR,
        '__destruct' => self::STAGE_DESTRUCTOR,
        '__call' => self::STAGE_MAGIC_METHODS,
        '__callStatic' => self::STAGE_MAGIC_METHODS,
        '__get' => self::STAGE_MAGIC_METHODS,
        '__set' => self::STAGE_MAGIC_METHODS,
        '__isset' => self::STAGE_MAGIC_METHODS,
        '__unset' => self::STAGE_MAGIC_METHODS,
        '__sleep' => self::STAGE_MAGIC_METHODS,
        '__wakeup' => self::STAGE_MAGIC_METHODS,
        '__toString' => self::STAGE_MAGIC_METHODS,
        '__invoke' => self::STAGE_MAGIC_METHODS,
        '__set_state' => self::STAGE_MAGIC_METHODS,
        '__clone' => self::STAGE_MAGIC_METHODS,
        '__debugInfo' => self::STAGE_MAGIC_METHODS,
    ];

    private const STAGE_TOKEN_NAMES = [
        self::STAGE_USES => 'use',
        self::STAGE_PUBLIC_CONSTANTS => 'public constant',
        self::STAGE_PROTECTED_CONSTANTS => 'protected constant',
        self::STAGE_PRIVATE_CONSTANTS => 'private constant',
        self::STAGE_PUBLIC_STATIC_PROPERTIES => 'public static property',
        self::STAGE_PROTECTED_STATIC_PROPERTIES => 'protected static property',
        self::STAGE_PRIVATE_STATIC_PROPERTIES => 'private static property',
        self::STAGE_PUBLIC_PROPERTIES => 'public property',
        self::STAGE_PROTECTED_PROPERTIES => 'protected property',
        self::STAGE_PRIVATE_PROPERTIES => 'private property',
        self::STAGE_PUBLIC_STATIC_METHODS => 'public static method',
        self::STAGE_PROTECTED_STATIC_METHODS => 'protected static method',
        self::STAGE_PRIVATE_STATIC_METHODS => 'private static method',
        self::STAGE_CONSTRUCTOR => 'constructor',
        self::STAGE_STATIC_CONSTRUCTORS => 'static constructor',
        self::STAGE_DESTRUCTOR => 'destructor',
        self::STAGE_MAGIC_METHODS => 'magic method',
        self::STAGE_PUBLIC_METHODS => 'public method',
        self::STAGE_PROTECTED_METHODS => 'protected method',
        self::STAGE_PRIVATE_METHODS => 'private method',
    ];

    /** @var int[] */
    public $requiredOrder = [
        self::STAGE_NONE => 0,
        self::STAGE_USES => 10,
        self::STAGE_PUBLIC_CONSTANTS => 20,
        self::STAGE_PROTECTED_CONSTANTS => 30,
        self::STAGE_PRIVATE_CONSTANTS => 40,
        self::STAGE_PUBLIC_STATIC_PROPERTIES => 50,
        self::STAGE_PROTECTED_STATIC_PROPERTIES => 60,
        self::STAGE_PRIVATE_STATIC_PROPERTIES => 70,
        self::STAGE_PUBLIC_PROPERTIES => 80,
        self::STAGE_PROTECTED_PROPERTIES => 90,
        self::STAGE_PRIVATE_PROPERTIES => 100,
        self::STAGE_PUBLIC_STATIC_METHODS => 110,
        self::STAGE_PROTECTED_STATIC_METHODS => 120,
        self::STAGE_PRIVATE_STATIC_METHODS => 130,
        self::STAGE_CONSTRUCTOR => 140,
        self::STAGE_STATIC_CONSTRUCTORS => 150,
        self::STAGE_DESTRUCTOR => 160,
        self::STAGE_MAGIC_METHODS => 170,
        self::STAGE_PUBLIC_METHODS => 180,
        self::STAGE_PROTECTED_METHODS => 190,
        self::STAGE_PRIVATE_METHODS => 200,
    ];

    /**
     * @return int[]
     */
    public function register() : array
    {
        return Tokens::$ooScopeTokens;
    }

    /**
     * @param int $pointer
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     */
    public function process(File $file, $pointer) : int
    {
        $tokens = $file->getTokens();
        $rootScopeToken = $tokens[$pointer];
        $rootScopeOpenerPointer = $rootScopeToken['scope_opener'];
        $rootScopeCloserPointer = $rootScopeToken['scope_closer'];

        $stageTokenTypes = [T_USE, T_CONST, T_VAR, T_STATIC, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_FUNCTION];

        $currentTokenPointer = $rootScopeOpenerPointer + 1;
        $expectedStage = self::STAGE_NONE;
        do {
            $currentTokenPointer = $file->findNext($stageTokenTypes, $currentTokenPointer, $rootScopeCloserPointer);
            if (is_bool($currentTokenPointer)) {
                break;
            }

            $currentToken = $tokens[$currentTokenPointer];
            if ($currentToken['level'] - $rootScopeToken['level'] !== 1) {
                continue;
            }

            $stage = $this->getStageForToken($file, $currentTokenPointer);
            if ($stage !== null) {
                if ($this->requiredOrder[$stage] < $this->requiredOrder[$expectedStage]) {
                    $fix = $file->addFixableError(
                        sprintf('The placement of %s is invalid.', self::STAGE_TOKEN_NAMES[$stage]),
                        $currentTokenPointer,
                        self::CODE_INVALID_MEMBER_PLACEMENT
                    );
                    if ($fix) {
                        $this->fixInvalidMemberPlacement($file, $currentTokenPointer);

                        return $pointer - 1; // run the sniff again to fix members one by one
                    }
                } elseif ($this->requiredOrder[$stage] > $this->requiredOrder[$expectedStage]) {
                    $expectedStage = $stage;
                }
            }

            $currentTokenPointer = $currentToken['scope_closer'] ?? $currentTokenPointer + 1;
        } while ($currentTokenPointer !== false);

        return $pointer + 1;
    }

    private function getStageForToken(File $file, int $pointer) : ?int
    {
        $tokens = $file->getTokens();

        switch ($tokens[$pointer]['code']) {
            case T_USE:
                return self::STAGE_USES;
            case T_CONST:
                switch ($this->getVisibilityForToken($file, $pointer)) {
                    case T_PUBLIC:
                        return self::STAGE_PUBLIC_CONSTANTS;
                    case T_PROTECTED:
                        return self::STAGE_PROTECTED_CONSTANTS;
                    default:
                        return self::STAGE_PRIVATE_CONSTANTS;
                }
                // never reached
            case T_FUNCTION:
                $name = strtolower($tokens[$file->findNext(T_STRING, $pointer + 1)]['content']);
                if (array_key_exists($name, self::SPECIAL_METHODS)) {
                    return self::SPECIAL_METHODS[$name];
                }

                $isStatic = $this->isMemberStatic($file, $pointer);
                if ($this->isStaticConstructor($file, $pointer, $isStatic)) {
                    return self::STAGE_STATIC_CONSTRUCTORS;
                }

                switch ($this->getVisibilityForToken($file, $pointer)) {
                    case T_PUBLIC:
                        return $isStatic ? self::STAGE_PUBLIC_STATIC_METHODS : self::STAGE_PUBLIC_METHODS;
                    case T_PROTECTED:
                        return $isStatic ? self::STAGE_PROTECTED_STATIC_METHODS : self::STAGE_PROTECTED_METHODS;
                    default:
                        return $isStatic ? self::STAGE_PRIVATE_STATIC_METHODS : self::STAGE_PRIVATE_METHODS;
                }
                // never reached
            default:
                $nextPointer = TokenHelper::findNextEffective($file, $pointer + 1);
                if ($tokens[$nextPointer]['code'] !== T_VARIABLE) {
                    return null;
                }

                $previousPointer = TokenHelper::findPreviousEffective($file, $pointer - 1);
                $visibility = $tokens[$previousPointer]['code'];
                if (!in_array($visibility, Tokens::$scopeModifiers, true)) {
                    $visibility = T_PUBLIC;
                }

                $isStatic = $this->isMemberStatic($file, $pointer);

                switch ($visibility) {
                    case T_PUBLIC:
                        return $isStatic ? self::STAGE_PUBLIC_STATIC_PROPERTIES : self::STAGE_PUBLIC_PROPERTIES;
                    case T_PROTECTED:
                        return $isStatic
                            ? self::STAGE_PROTECTED_STATIC_PROPERTIES
                            : self::STAGE_PROTECTED_PROPERTIES;
                    default:
                        return $isStatic ? self::STAGE_PRIVATE_STATIC_PROPERTIES : self::STAGE_PRIVATE_PROPERTIES;
                }
        }
    }

    private function getVisibilityForToken(File $file, int $pointer) : int
    {
        $tokens = $file->getTokens();

        $tokensToIgnore = array_merge(Tokens::$emptyTokens, [T_ABSTRACT, T_STATIC, T_FINAL]);
        $prevPointer = $file->findPrevious($tokensToIgnore, $pointer - 1, null, true, null, true);
        if ($prevPointer !== false && in_array($tokens[$prevPointer]['code'], Tokens::$scopeModifiers, true)) {
            $visibility = $tokens[$prevPointer]['code'];
        }

        return $visibility ?? T_PUBLIC;
    }

    private function isMemberStatic(File $file, int $pointer) : bool
    {
        $tokens = $file->getTokens();
        $tokenTypes = [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_STATIC];
        $previousPointer = $file->findPrevious($tokenTypes, $pointer, null, false, null, true);

        return $tokens[$previousPointer]['code'] === T_STATIC;
    }

    private function isStaticConstructor(File $file, int $pointer, bool $isStatic) : bool
    {
        if (!$isStatic) {
            return false;
        }

        if ($this->getVisibilityForToken($file, $pointer) !== T_PUBLIC) {
            return false;
        }

        $parentClassName = $this->getParentClassName($file, $pointer);

        $returnTypeHint = FunctionHelper::findReturnTypeHint($file, $pointer);
        if ($returnTypeHint !== null) {
            return in_array($returnTypeHint->getTypeHint(), ['self', $parentClassName], true);
        }

        $returnAnnotation = FunctionHelper::findReturnAnnotation($file, $pointer);
        if ($returnAnnotation === null) {
            return false;
        }

        return in_array($returnAnnotation->getContent(), ['static', 'self', $parentClassName], true);
    }

    private function getParentClassName(File $file, int $pointer) : string
    {
        $classPointer = TokenHelper::findPrevious($file, Tokens::$ooScopeTokens, $pointer - 1);
        if ($classPointer === null) {
            return '';
        }

        return ClassHelper::getName($file, $classPointer);
    }

    private function fixInvalidMemberPlacement(File $file, int $pointer) : void
    {
        $tokens = $file->getTokens();
        $endTypes = [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON];
        $previousMemberEndPointer = TokenHelper::findPrevious($file, $endTypes, $pointer - 1);
        if ($previousMemberEndPointer === null) {
            throw new RuntimeException('Previous member end pointer not found');
        }

        $startPointer = $this->findMemberLineStartPointer($file, $pointer, $previousMemberEndPointer);

        if ($tokens[$pointer]['code'] === T_FUNCTION && !FunctionHelper::isAbstract($file, $pointer)) {
            $endPointer = $tokens[$pointer]['scope_closer'];
        } else {
            $endPointer = TokenHelper::findNext($file, T_SEMICOLON, $pointer + 1);
            if ($endPointer === null) {
                throw new RuntimeException('End pointer not found');
            }
        }

        $possibleWhitespaceTypes = [T_COMMENT, T_DOC_COMMENT, T_DOC_COMMENT_WHITESPACE, T_WHITESPACE];
        $whitespacePointer = $file->findNext($possibleWhitespaceTypes, $endPointer + 1, null, false, "\n");
        $nextEffectivePointer = TokenHelper::findNextEffective($file, $endPointer + 1);
        if ($whitespacePointer < $nextEffectivePointer) {
            $endPointer = $whitespacePointer;
        }

        if ($tokens[$previousMemberEndPointer]['code'] === T_CLOSE_CURLY_BRACKET) {
            $previousScopeOpenerPointer = $tokens[$previousMemberEndPointer]['scope_opener'];
            $prePreviousMemberEndPointer = TokenHelper::findPrevious($file, $endTypes, $previousScopeOpenerPointer - 1);
        } else {
            $prePreviousMemberEndPointer = TokenHelper::findPrevious($file, $endTypes, $previousMemberEndPointer - 1);
        }

        if ($prePreviousMemberEndPointer === null) {
            throw new RuntimeException('Pre-previous member end pointer not found');
        }

        $previousMemberStartPointer = TokenHelper::findNextEffective($file, $prePreviousMemberEndPointer + 1);
        if ($previousMemberStartPointer === null) {
            throw new RuntimeException('Previous member start pointer not found');
        }

        $previousMemberStartPointer = $this->findMemberLineStartPointer(
            $file,
            $previousMemberStartPointer,
            $prePreviousMemberEndPointer
        );

        $linesBetween = (int) $tokens[$startPointer]['line'] - (int) $tokens[$previousMemberEndPointer]['line'] - 1;

        $file->fixer->beginChangeset();

        $content = '';
        for ($i = $startPointer; $i <= $endPointer; $i++) {
            $content .= $tokens[$i]['content'];
            $file->fixer->replaceToken($i, '');
        }

        $this->removeBlankLinesAfterMember($file, $linesBetween, $previousMemberEndPointer, $startPointer);

        $newLines = str_repeat($file->eolChar, $linesBetween);
        $file->fixer->addContentBefore($previousMemberStartPointer, $content . $newLines);

        $file->fixer->endChangeset();
    }

    private function findMemberLineStartPointer(
        File $file,
        int $memberStartPointer,
        int $previousMemberEndPointer
    ) : int {
        $types = [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON];

        $startPointer = DocCommentHelper::findDocCommentOpenToken($file, $memberStartPointer);
        if ($startPointer === null) {
            $startPointer = TokenHelper::findNextEffective($file, $previousMemberEndPointer + 1);
            if ($startPointer === null) {
                throw new RuntimeException('Start pointer not found');
            }
        }

        return (int) $file->findFirstOnLine($types, $startPointer, true);
    }

    private function removeBlankLinesAfterMember(
        File $file,
        int $linesToRemove,
        int $memberEndPointer,
        int $endPointer
    ) : void {
        $tokens = $file->getTokens();
        $whitespacePointer = $memberEndPointer;
        while ($linesToRemove > 0) {
            $whitespacePointer = TokenHelper::findNext($file, T_WHITESPACE, $whitespacePointer, $endPointer);
            if ($whitespacePointer === null) {
                break;
            }

            if ($tokens[$whitespacePointer]['length'] !== 0) {
                $whitespacePointer++;
                continue;
            }

            $linesToRemove--;
            $file->fixer->replaceToken($whitespacePointer++, '');
        }
    }
}
