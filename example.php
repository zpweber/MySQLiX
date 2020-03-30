<?php

private static function ParseConstraints(RouteParserContext $context){
    $constraints = [];
    
    while( true ){
        switch( $context->findNextIn(':=?{}') ){
            case ':':
                $context->step();
                $context->mark();

                /* Get constraint name */
                while( true ){
                    switch( $context->findNextIn('\(:=?{}') ){
                        case '\\':
                            /* Skip valid escaped chars in constraint name (edge-case but needs to be at start of switch) */
                            if( \strpbrk($context->next(), '\():=?') !== false ){
                                /* Skip escaped */
                                $context->step(2);
                            }else{
                                /* Literal non-escaping '\': Skip once */
                                $context->step();
                            }

                            break;
                        case '}':
                            if( $context->next() === '}' ){
                                $context->step();
                                break;
                            }/* Else continue down */
                        case '(':
                        case ':':
                        case '=':
                        case '?':
                            /* Finished parsing current constraint name: exit loop */
                            break 2;
                        case null:
                            $context->exception(new \Exception(''));
                            return false;

                        /* More edge cases */
                        case '{':
                            if( $context->next() !== '{' ){
                                $context->exception(new \Exception(''));
                                return false; 
                            }
                            /* Else escaped brace: Skip */
                            $context->step();
                            break;
                    }
                }

                $name = $context->capture();
                $constraints[$name] = [$name];

                /* Does constraint point to mapped function? If so, parse here: */
                if( $context->current() === '(' ){
                    $context->step();
                    $context->mark();
                    $argumentsString = null;
                    $parenthesesDepth = 1;

                    while( true ){
                        switch( $context->findNextIn('\()') ){
                            case '\\':
                                if( \strpbrk($context->next(), '\[]()') !== false ){
                                    $context->step(2);
                                }else{
                                    $context->step();
                                }

                                break;
                            case ')':
                                $parenthesesDepth--;

                                if( $parenthesesDepth <= 0 ){
                                    break 2;
                                }

                                $context->step();
                                break;
                            case '(':
                                $parenthesesDepth++;
                                $context->step();
                                break;
                            case null:
                                $context->exception(new \Exception(''));
                                return false;
                        }
                    }

                    $constraints[$name][] = $context->capture();
                }

                break;
                /* End constraint */
            case '}':
                if( $context->next() === '}' ){
                    $context->exception(new \Exception(''));
                    return false;
                }
            case '=':
            case '?':
                break 2;
            case null:
                $context->exception(new \Exception(''));
                return false;
            default:
                $context->exception(new \Exception(''));
                return false;
        }
    }

    return true;
}
