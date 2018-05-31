<?php

error_reporting(E_ALL); ini_set('display_errors', 1);

require "common.php";

$actions = Array(
    'init' => function ($path)
    {
        if (!file_exists("$path"))
        {
            foreach(range('a','e') as $identity)
            {
                mkdir("$path/$identity", 0755, true);
                /* file_put_contents("$path/$identity/data.php", '<?php include("../../data.php") ?>');*/
                file_put_contents("$path/$identity/index.php", '<?php include("../../shard.php") ?>');
            }
            
            file_put_contents("$path/.gitignore", "*\n");
        }
    },
    'group' => function ($args)
    {    
        $storage = new ShardDrive(shards());
        return $storage->group();
    },
    'create' => function ($args)
    {

    },
    'read' => function ($args)
    {

    }
);

$args = explode('&', $_SERVER['QUERY_STRING']);
$method = array_shift($args);

if (!file_exists("shards"))
{
    $actions['init']('shards');
}

if (isset($actions[$method]) && is_callable($actions[$method]))
{
    echo $actions[$method]($args);
}

?>