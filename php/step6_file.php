<?php

require_once 'readline.php';
require_once 'types.php';
require_once 'reader.php';
require_once 'printer.php';
require_once 'env.php';
require_once 'core.php';

// read
function READ($str) {
    return read_str($str);
}

// eval
function eval_ast($ast, $env) {
    if (_symbol_Q($ast)) {
        return $env->get($ast->value);
    } elseif (_sequential_Q($ast)) {
        if (_list_Q($ast)) {
            $el = _list();
        } else {
            $el = _vector();
        }
        foreach ($ast as $a) { $el[] = MAL_EVAL($a, $env); }
        return $el;
    } elseif (_hash_map_Q($ast)) {
        $new_hm = _hash_map();
        foreach (array_keys($ast->getArrayCopy()) as $key) {
            $new_hm[$key] = MAL_EVAL($ast[$key], $env);
        }
        return $new_hm;
    } else {
        return $ast;
    }
}

function MAL_EVAL($ast, $env) {
    while (true) {

    #echo "MAL_EVAL: " . _pr_str($ast) . "\n";
    if (!_list_Q($ast)) {
        return eval_ast($ast, $env);
    }

    // apply list
    $a0 = $ast[0];
    $a0v = (_symbol_Q($a0) ? $a0->value : $a0);
    switch ($a0v) {
    case "def!":
        $res = MAL_EVAL($ast[2], $env);
        return $env->set($ast[1]->value, $res);
    case "let*":
        $a1 = $ast[1];
        $let_env = new Env($env);
        for ($i=0; $i < count($a1); $i+=2) {
            $let_env->set($a1[$i]->value, MAL_EVAL($a1[$i+1], $let_env));
        }
        return MAL_EVAL($ast[2], $let_env);
    case "do":
        eval_ast($ast->slice(1, -1), $env);
        $ast = $ast[count($ast)-1];
        break;
    case "if":
        $cond = MAL_EVAL($ast[1], $env);
        if ($cond === NULL || $cond === false) {
            if (count($ast) === 4) { $ast = $ast[3]; }
            else                   { $ast = NULL; }
        } else {
            $ast = $ast[2];
        }
        break;
    case "fn*":
        return _function('MAL_EVAL', 'native',
                         _hash_map('exp', $ast[2],
                                   'env', $env,
                                   'params', $ast[1]));
    default:
        $el = eval_ast($ast, $env);
        $f = $el[0];
        $args = array_slice($el->getArrayCopy(), 1);
        if ($f->type === 'native') {
            $ast = $f->meta['exp'];
            $env = new Env($f->meta['env'], $f->meta['params'], $args);
        } else {
            return $f->apply($args);
        }
    }

    }
}

// print
function MAL_PRINT($exp) {
    return _pr_str($exp, True) . "\n";
}

// repl
$repl_env = new Env(NULL);
function rep($str) {
    global $repl_env;
    return MAL_PRINT(MAL_EVAL(READ($str), $repl_env));
}
function _ref($k, $v) {
    global $repl_env;
    $repl_env->set($k, _function($v));
}
// Import core functions
foreach ($core_ns as $k=>$v) { _ref($k, $v); }

_ref('read-string', 'read_str');
_ref('eval', function($ast) {
    global $repl_env; return MAL_EVAL($ast, $repl_env);
});
_ref('slurp', function($f) {
    return file_get_contents($f);
});

// Defined using the language itself
rep("(def! not (fn* (a) (if a false true)))");
rep("(def! load-file (fn* (f) (eval (read-string (str \"(do \" (slurp f) \")\")))))");

if (count($argv) > 1) {
    for ($i=1; $i < count($argv); $i++) {
        rep('(load-file "' . $argv[$i] . '")');
    }
} else {
    do {
        try {
            $line = mal_readline("user> ");
            if ($line === NULL) { break; }
            if ($line !== "") {
                print(rep($line));
            }
        } catch (BlankException $e) {
            continue;
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
        }
    } while (true);
}

?> 
