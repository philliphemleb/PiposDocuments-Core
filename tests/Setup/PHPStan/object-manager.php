<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';

new Dotenv()->bootEnv(\dirname(__DIR__, 3) . '/.env');

$env = $_SERVER['APP_ENV'] ?? 'dev';
\assert(\is_string($env));

$debug = filter_var($_SERVER['APP_DEBUG'] ?? '0', \FILTER_VALIDATE_BOOLEAN);

$kernel = new Kernel($env, $debug);
$kernel->boot();

/** @var Registry $doctrine */
$doctrine = $kernel->getContainer()->get('doctrine');

return $doctrine->getManager();
