<?php
use TYPO3\Surf\Domain\Model\Node;
use TYPO3\Surf\Domain\Model\SimpleWorkflow;
use TYPO3\Surf\Application\TYPO3\Neos;

/**
 * For this deployment the following env variables are required:
 *
 * DEPLOYMENT_PATH: path on the remote server to deploy to
 * DEPLOYMENT_USER: username to connect to the remote server
 * DEPLOYMENT_HOST: node host name
 */

$application = new Neos();
if (getenv('DEPLOYMENT_PATH')) {
        $application->setDeploymentPath(getenv('DEPLOYMENT_PATH'));
} else {
        throw new \Exception('Deployment path must be set in the DEPLOYMENT_PATH env variable.');
}

$application->setContext('Production/Neosdemotypo3org');
$application->setOption('repositoryUrl', 'https://github.com/neos/neos-demo-distribution.git');
$application->setOption('typo3.surf:gitCheckout[branch]', 'stable');
$application->setOption('sitePackageKey', 'TYPO3.NeosDemoTypo3Org');
$application->setOption('keepReleases', 20);
$application->setOption('composerCommandPath', 'php /var/www/neos.demo.typo3.org/home/composer.phar');

$deployment->addApplication($application);

$workflow = new SimpleWorkflow();
$deployment->setWorkflow($workflow);

$deployment->onInitialize(function() use ($workflow, $application) {
        $workflow->removeTask('typo3.surf:flow:setfilepermissions');
        $workflow->removeTask('typo3.surf:typo3:flow:copyconfiguration');
});

if (getenv('DEPLOYMENT_HOST')) {
        $hostName = getenv('DEPLOYMENT_HOST');
} else {
        throw new \Exception('Deployment host name must be set in the DEPLOYMENT_HOST env variable.');
}

$workflow->defineTask('x:updatecomposer', 'typo3.surf:shell', array('command' => 'php /var/www/neos.demo.typo3.org/home/composer.phar self-update'));
$workflow->beforeStage('update', array('x:updatecomposer'), $application);

$workflow->defineTask('x:publishresources', 'typo3.surf:typo3:flow:runcommand', array('command' => 'resource:publish'));
$workflow->afterTask('typo3.surf:typo3:flow:migrate', array('x:publishresources'), $application);

// Create frontend user
$workflow->defineTask('x:frontenduser', 'typo3.surf:typo3:flow:runcommand', array('command' => 'typo3.neos:user:create member password Frontend User --authentication-provider "Flowpack.Neos.FrontendLogin:Frontend" --roles "Flowpack.Neos.FrontendLogin:User" > /dev/null || exit 0'));
$workflow->forStage('finalize', array('x:frontenduser'));

$node = new Node($hostName);
$node->setHostname($hostName);
if (getenv('DEPLOYMENT_USERNAME')) {
        $node->setOption('username', getenv('DEPLOYMENT_USERNAME'));
} else {
        throw new \Exception('Username must be set in the DEPLOYMENT_USERNAME env variable.');
}

$application->addNode($node);
