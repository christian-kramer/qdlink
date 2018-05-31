<?php

error_reporting(E_ALL); ini_set('display_errors', 1);

$actions = Array(
    'token' => function()
    {
        return json_encode(['token' => issue_token()]);
    },
    'shorten' => function()
    {
        $data = data(['token', 'url', 'custom']);
        
        $token = $data['token'];
        $url = $data['url'];
        $block = $data['custom'];

        $group = verify_token($token);

        if (empty($group))
        {
            return error(true, 'Invalid Token');
        }

        var_dump($group);
        exit;

        if ($token && filter_var($url, FILTER_VALIDATE_URL) && !$block || ctype_alnum($block))
        {
            $link = Array('url' => $url);
            $result = $sharddrive->store(json_encode($link), $block, 'links', $token);
            echo json_encode($result);
        }
        return $value;
    },
    'account' => Array(
        'login' => function()
        {
            return $value;
        },
        'register' => function ()
        {
            return $value;
        },
        'details' => function ()
        {
            return $value;
        }
    )
);

/* Handle Requested Method */
route(explode('/', $_SERVER['SCRIPT_NAME']), $actions);

//echo "no valid method called";

$query = $_SERVER['QUERY_STRING'];
if (!empty($query) && strlen($query) < 200)
{
    if (ctype_alpha($query))
    {
        /* redirect */
        echo "redirecting to $query";
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








/* Bootstrap Environment if Necessary */

build(".", $actions);


/* serve homepage */
echo '<h1>Welcome to qdl.ink</h1><form><div><label for="uname">Enter link to shorten: </label><input type="text" id="link" name="link"></div><div><button>Submit</button></div></form>';


exit;








function issue_token()
{
    $group = file_get_contents("http://storage.qdl.ink/?group");
    if ($group)
    {
        $time = round((microtime(true) + 10) * 100000) % 360000000;
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
    $hash = sha1($token);
    $group = '';
    $path = journal(__DIR__ . "/data/tokens/");

    journal($hash);

    foreach ($hours as $hour)
    {
        if (file_exists("$path/$hour/$hash"))
        {
            $group = file_get_contents("$path/$hour/$hash");

            while (file_exists("$path/$hour/$hash"))
            {
                if (!empty($group))
                {
                    unlink("$path/$hour/$hash");
                }
            }
        }
    }

    return $group;
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
            if (count($request) - 1)
            {
                route($request, $actions[$context]);
            }
            else
            {
                $path = journal('html' . dirname($_SERVER['SCRIPT_NAME']));

                if (file_exists("$path/index.html"))
                {
                    echo file_get_contents($path);
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
        if (count($request) - 1)
        {
            route($request, $actions);
        }
    }
}

function data($keys)
{
    $values = Array();
    foreach ($keys as $key)
    {
        $values[$key] = isset($_GET[$key]) ? $_GET[$key] : '';
    }
    return $values;


    var_dump($_GET);
    exit;

    if (isset($_POST[0]) || isset($_GET[0]))
    {

    }

    $post = $_POST ?? $_GET;
    //$data = count($post) ? $post : json_decode(file_get_contents('php://input'), true);
    //$data = json_decode(file_get_contents('php://input'), true);



    $data = (array)$_POST;
    var_dump($data);
    exit;
    return $data;
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

function error($failed, $message)
{
    $status = Array('SUCCESS', 'ERROR')[$failed];
    $response = Array('status' => $status, 'response' => journal($message));
    return json_encode($response);
}

?>