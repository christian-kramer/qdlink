<?php

//error_reporting(E_ALL); ini_set('display_errors', 1);

define('STORAGE', 'http://storage.dev.qdl.ink');
define('ALPHABET', range('a', 'e'));
define('APPID', 'salt');

$actions = Array(
    'token' => function ()
    {
        //setcookie("token", 'hi christian', 0, '/', null, false, true);
        identify();
        return json_encode(['token' => issue_token()]);
    },
    'shorten' => function ()
    {
        extract(data(['token', 'url', 'custom']));

        $hash = safe($_COOKIE['account']) ?? null;

        if (empty($url))
        {
            return error(true, 'no URL provided');
        }

        /* verify_token provides a load-balanced group, but also throttles transactions */
        $validation = json_decode(verify_token($token), true);

        if ($validation['status'] === 'ERROR')
        {
            return json_encode($validation);
        }
        else
        {
            $group = $validation['response'];
        }

        if (!empty($custom))
        {
            $group = $custom;
        }
        
        $link = Array('url' => $url);
        //$result = $sharddrive->store(json_encode($link), $block, 'links', $token);
        $result = json_decode(post_json(STORAGE . "/create/?link&$group", Array('data' => $link)), true);
        if ($result && $result['status'] === 'SUCCESS')
        {
            post_json(STORAGE . "/update/?account&$hash", Array('data' => array('links' => array($result['response']))));
            return error(false, $result['response']);
        }
        if ($result['status'] === 'ERROR')
        {
            return error(true, $result['response']);
        }
        else
        {
            return error(true, 'error establishing a database connection');
        }
    },
    'account' => Array(
        'login' => function ()
        {
            extract(data(['username', 'password']));

            if (empty($username) || empty($password))
            {
                return error(true, 'invalid parameters');
            }

            $userid = safe($username);
            $user = json_decode(file_get_contents(STORAGE . "/read/?uid&$userid"));
            $hash = safe($user->account);
            $account = json_decode(file_get_contents(STORAGE . "/read/?account&$hash"));

            if (password_verify($password, $account->password))
            {
                setcookie("account", $user->account, 0, '/', null, false, true);
                return error(false, "$user->account");
            }
            else
            {
                return error(true, "authentication failed");
            }
        },
        'register' => function ()
        {
            $hash = safe($_COOKIE['account']);

            extract(data(['username', 'password']));

            if (empty($username) || empty($password) || empty($hash))
            {
                return error(true, 'invalid parameters');
            }
            
            $userid = safe($username);

            /* retrieve account file */
            $account = json_decode(file_get_contents(STORAGE . "/read/?account&$hash"));

            /* write password property to account file */
            $account->password = password_hash($password, PASSWORD_DEFAULT);
            journal("writing password to /update/?account&$hash");
            $result = json_decode(post_json(STORAGE . "/update/?account&$hash", Array('data' => $account)), true);
            if (!$result || (isset($result['status']) && $result['status'] === 'ERROR'))
            {
                return $result;
            }

            /* write account number to user file */
            journal("writing " . $_COOKIE['account'] . " account to /update/?uid&$userid");
            $result = json_decode(post_json(STORAGE . "/update/?uid&$userid", Array('data' => array('account' => $_COOKIE['account']))), true);
            if (!$result || (isset($result['status']) && $result['status'] === 'ERROR'))
            {
                return $result;
            }

            return json_encode($result);
        },
        'details' => function ()
        {
            $hash = safe($_COOKIE['account']);
            return file_get_contents(STORAGE . "/props/?account&$hash");
        },
        'links' => function ()
        {
            $hash = safe($_COOKIE['account']);
            
            $linklist = json_decode(file_get_contents(STORAGE . "/read/?account&$hash"))->links;

            foreach ($linklist as $linkid)
            {
                $linkdetails = json_decode(file_get_contents(STORAGE . "/props/?link&$linkid"));

                $links->$linkid->clicks = $linkdetails->reads ?? 0;
                $links->$linkid->longurl = $linkdetails->data->url;
                $links->$linkid->shorturl = "http://" . $_SERVER['HTTP_HOST'] . "?$linkid";
            }

            return json_encode($links);
        }
    )
);

/* Handle Requested Method */
route(explode('/', $_SERVER['SCRIPT_NAME']), $actions);

//echo "no valid method called";


/* Handle General Usage */
$query = $_SERVER['QUERY_STRING'];
if (!empty($query) && strlen($query) < 200)
{
    if (ctype_alpha($query))
    {
        /* redirect */
        $storage = json_decode(file_get_contents(STORAGE . "/read?link&$query"), true);

        if ($storage)
        {
            if (isset($storage['status']) && $storage['status'] === 'ERROR')
            {
                echo error(true, $storage['response']);
            }
            else
            {
                header("Location: " . $storage['url']);
            }
            exit;
        }
        echo error(true, 'could not connect to database');
        exit;
    }
    else
    {
        /* url to shorten */
        if (filter_var($query, FILTER_VALIDATE_URL))
        {
            echo "shortening $query";
            exit;
        }
        else
        {
            if (filter_var("http://$query", FILTER_VALIDATE_URL))
            {
                echo "fixing and shortening http://$query";
                exit;
            }
        }
    }
}


/* Handle all other cases */

/* Bootstrap Environment if Necessary */
build(".", $actions);

/* serve homepage */
route(['default'], ['default' => []]);

exit;








function issue_token()
{
    $group = file_get_contents(STORAGE . "/group");
    $delay = 0.5;
    if ($group)
    {
        $time = round((microtime(true) + $delay) * 10000) % 36000000;
        $token = journal(dechex(strrev($time) + 268435456));
        //$token = (time() + 1) * rand(1, 9);
        $hash = sha1($token);
        $hour = date('YmdH');
        $path = journal("../data/tokens/$hour");
        if (file_exists($path) || mkdir($path, 0755, true))
        {
            file_put_contents("$path/$hash", $group);
        }
        return substr($token, 1, 7);
    }
    else
    {
        return issue_token();
    }
}

function verify_token($fragment)
{
    $hours = Array(date('YmdH'), date('YmdH', strtotime('-1 hour')));
    $token = '1' . $fragment;
    $valid = strrev(hexdec($token) - 268435456);
    $time = round((microtime(true)) * 10000) % 36000000;
    $hash = sha1($token);
    $group = '';
    $path = journal(__DIR__ . "/data/tokens/");

    journal("valid at $valid, time is $time");
    if ($valid > $time)
    {
        return error(true, 'You are too fast, please take a rest...');
    }

    foreach ($hours as $hour)
    {
        if (file_exists("$path/$hour/$hash"))
        {
            $group = file_get_contents("$path/$hour/$hash");

            while (file_exists("$path/$hour/$hash"))
            {
                if (!empty($group))
                {
                    journal("unlinking $hour/$hash");
                    unlink("$path/$hour/$hash");
                }
            }
        }
    }

    if (empty($group))
    {
        return error(true, 'invalid token');
    }

    return error(false, $group);
}

function identify()
{
    if (!empty($_COOKIE['account']))
    {
        return error(false, $_COOKIE['account']);
    }

    $account = uniqid();
    $hash = safe($account);
    
    $result = json_decode(post_json(STORAGE . "/create/?account&$hash", Array('data' => array('links' => array()))), true);
    if ($result && $result['status'] === 'SUCCESS')
    {
        setcookie("account", $account, 0, '/', null, false, true);
        return error(false, "$account");
    }
    if ($result['status'] === 'ERROR')
    {
        return error(true, $result['response']);
    }
    else
    {
        return error(true, 'error establishing a database connection');
    }
}

function build($path, $actions)
{
    $include = '<?php include $_SERVER["DOCUMENT_ROOT"] . "/index.php" ?>';
    foreach ($actions as $name => $value)
    {
        if (!file_exists("$path/$name"))
        {
            mkdir("$path/$name", 0755, true);
            
            if (is_array($value))
            {
                build("$path/$name", $value);
            }

            file_put_contents("$path/$name/index.php", $include);
        }
    }
}



function route($request, $actions)
{
    $context = array_shift($request);

    if (isset($actions[$context]))
    {
        if (is_callable($actions[$context]))
        {
            echo $actions[$context]();
            exit;
        }
        
        if (is_array($actions[$context]))
        {
            if (count($request) > 0)
            {
                route($request, $actions[$context]);
            }
            else
            {
                $path = journal('html' . dirname($_SERVER['SCRIPT_NAME']));

                if (file_exists("$path/index.html"))
                {
                    echo file_get_contents("$path/index.html");
                }
                else
                {
                    http_response_code(404);
                    header('HTTP/1.0 404 Not Found', true, 404);
                    echo "<h1>404 Not Found</h1>";
                    exit;
                }

                exit;
            }
        }
    }
    else
    {
        if (count($request) > 0)
        {
            route($request, $actions);
        }
    }
}

function data($keys)
{
    $values = Array();
    $keys = array_flip($keys);
    
    $jsonpost = empty(file_get_contents("php://input")) ? array() : array_intersect_key(json_decode(file_get_contents("php://input"), true), $keys);
    $formpost = empty($_POST) ? array() : array_intersect_key($_POST, $keys);
    $get = empty($_GET) ? array() : array_intersect_key($_GET, $keys);

    $values = array_merge($jsonpost, $formpost, $get);

    
    return $values;
}

function base26($decimal)
{
    $base = count(ALPHABET);
    $quotient = floor($decimal / $base);
    $remainder = $decimal % $base;
    return ($quotient ? base26($quotient) : '') . ALPHABET[$remainder];
}

function username_to_group($username)
{
    $myArray = str_split($username);
    $previous = NULL;
    $newArray = array_filter(
        $myArray,
        function ($value) use (&$previous) {
            $p = $previous;
            $previous = $value;
            return $value != $p;
        }
    );
    
    return substr(preg_replace("/[^a-z0-9 ]/", '', strtolower(implode('', $newArray))), 0, 2);
}


function journal($msg)
{
    $path = 'logs';
    $date = date('Y-m-d H:i:s');
    $file = date('Ymd');

    if ($msg && (file_exists($path) || (mkdir($path, 0755, true) && file_put_contents("$path/.gitignore", "*\n"))))
    {
        file_put_contents("$path/$file", "$date\t$msg\n", FILE_APPEND);
    }

    return $msg;
}

function sanitize($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function ipv6_numeric($ip) {
    $binNum = '';
    foreach (unpack('C*', inet_pton($ip)) as $byte) {
        $binNum .= str_pad(decbin($byte), 8, "0", STR_PAD_LEFT);
    }
    return base_convert(ltrim($binNum, '0'), 2, 10);
}

function safe($untrusted) {
    $hash = sha1($untrusted . APPID);
    return base26(hexdec($hash));
}

function post_json($url, $data)
{
    $opts = array('http' =>
        array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/json',
            'content' => json_encode($data)
        )
    );
    
    $context  = stream_context_create($opts);
    
    return file_get_contents($url, false, $context);
}

function error($failed, $message)
{
    $status = Array('SUCCESS', 'ERROR')[$failed];
    $response = Array('status' => $status, 'response' => journal($message));
    return json_encode($response);
}

?>