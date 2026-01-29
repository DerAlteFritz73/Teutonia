<?php
require 'vendor/autoload.php';
$c = new App\Controller\HomeController();
echo $c->index()->getContent();
