<?php

require '../Include/Config.php';

// This file is generated by Composer
require_once __DIR__ . '/../vendor/autoload.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\Authentication\Requests\LocalTwoFactorTokenRequest;
use Slim\Container;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\dto\SystemConfig;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$container = $app->getContainer();

$app->add(new VersionMiddleware());

// Set up
require __DIR__ . '/../Include/slim/error-handler.php';

require __DIR__ . '/routes/password-reset.php';


$app->get('/begin', 'beginSession');
$app->post("/begin", "beginSession");
$app->get('/end', 'endSession');
$app->get('/two-factor', 'processTwoFactorGet');
$app->post('/two-factor', 'processTwoFactorPost');

function processTwoFactorGet(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    $curUser = AuthenticationManager::getCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser,
    ];

    return $renderer->render($response, 'two-factor.php', $pageArgs);
}


function processTwoFactorPost(Request $request, Response $response, array $args)
{
    $loginRequestBody = (object)$request->getParsedBody();
    $request = new LocalTwoFactorTokenRequest($loginRequestBody->TwoFACode);
    AuthenticationManager::authenticate($request);
}

function endSession(Request $request, Response $response, array $args)
{
    AuthenticationManager::endSession();
}


function beginSession(Request $request, Response $response, array $args)
{
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'localAuthNextStepURL' => AuthenticationManager::getSessionBeginURL(),
        'forgotPasswordURL' => AuthenticationManager::getForgotPasswordURL()
    ];

    if ($request->getMethod() == "POST") {
        $loginRequestBody = (object)$request->getParsedBody();
        $request = new LocalUsernamePasswordRequest($loginRequestBody->User, $loginRequestBody->Password);
        $authenticationResult = AuthenticationManager::authenticate($request);
        $pageArgs['sErrorText'] = $authenticationResult->message;
    }

    $renderer = new PhpRenderer('templates/');

    $pageArgs['prefilledUserName'] = "";
    # Defermine if approprirate to pre-fill the username field
    if (isset($_GET['username'])) {
        $pageArgs['prefilledUserName'] = $_GET['username'];
    } elseif (isset($_SESSION['username'])) {
        $pageArgs['prefilledUserName'] = $_SESSION['username'];
    }

    return $renderer->render($response, 'begin-session.php', $pageArgs);
}

if (SystemConfig::debugEnabled()) {
    $app->addErrorMiddleware(true, true, true);
}

// Run app
$app->run();
