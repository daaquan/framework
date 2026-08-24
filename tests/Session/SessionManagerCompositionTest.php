<?php

use Phalcon\Di\Di;
use Phalcon\Flash\Session as FlashSession;
use Phalcon\Html\Escaper;
use Phalcon\Session\Adapter\Stream;
use Phalcon\Session\Manager as PhalconManager;
use Phalcon\Session\ManagerInterface;
use Phare\Contracts\Session\Session;
use Phare\Session\SessionManager;

function makeSessionManager(): SessionManager
{
    $session = (new SessionManager())->setAdapter(new Stream(['savePath' => sys_get_temp_dir()]));
    $session->start();

    return $session;
}

it('does not inherit from the phalcon session manager', function () {
    expect(is_subclass_of(SessionManager::class, PhalconManager::class))->toBeFalse();
});

it('implements the phare session contract', function () {
    expect(makeSessionManager())->toBeInstanceOf(Session::class);
});

it('keeps implementing the phalcon manager interface for phalcon interop', function () {
    // Phalcon\Flash\Session resolves the DI service named 'session' and requires
    // a Phalcon\Session\ManagerInterface. Composition removes the class
    // inheritance; the interface stays so that interop keeps working.
    expect(makeSessionManager())->toBeInstanceOf(ManagerInterface::class);
});

it('exposes the wrapped phalcon manager as an escape hatch', function () {
    expect(makeSessionManager()->getPhalconManager())->toBeInstanceOf(PhalconManager::class);
});

it('delegates the full manager surface to the wrapped instance', function () {
    $session = makeSessionManager();

    $session->set('a', 1);
    expect($session->get('a'))->toBe(1)
        ->and($session->has('a'))->toBeTrue()
        ->and($session->getId())->not->toBe('')
        ->and($session->getName())->not->toBe('')
        ->and($session->exists())->toBeTrue()
        ->and($session->status())->toBe(PHP_SESSION_ACTIVE)
        ->and($session->getAdapter())->toBeInstanceOf(SessionHandlerInterface::class)
        ->and($session->getOptions())->toBeArray();

    $session->remove('a');
    expect($session->has('a'))->toBeFalse();
});

it('supports the magic property accessors', function () {
    $session = makeSessionManager();

    $session->b = 'magic';
    expect(isset($session->b))->toBeTrue()
        ->and($session->b)->toBe('magic');

    unset($session->b);
    expect(isset($session->b))->toBeFalse();
});

it('returns itself from the fluent setters', function () {
    $session = makeSessionManager();

    // PHP sessions are process-global, so name/id setters are only legal before
    // start(); regenerateId and setAdapter are the ones reachable here.
    $before = $session->getId();
    expect($session->regenerateId())->toBe($session)
        ->and($session->getId())->not->toBe($before)
        ->and($session->setAdapter(new Stream(['savePath' => sys_get_temp_dir()])))->toBe($session);
});

it('setName and setOptions delegate before the session starts', function () {
    $fresh = new SessionManager();

    expect($fresh->setOptions(['uniqueId' => 'phare']))->toBeNull()
        ->and($fresh->getOptions())->toBe(['uniqueId' => 'phare']);
});

it('get() defaults to null when the key is missing', function () {
    expect(makeSessionManager()->get('definitely_missing'))->toBeNull();
});

it('still drives phalcon flash through the DI session service', function () {
    // Phalcon\Flash\Session pulls the service named 'session' out of the DI and
    // requires a Phalcon\Session\ManagerInterface. This is the reason
    // SessionManager keeps implementing that interface after dropping the
    // class inheritance, so it is worth a regression test.
    $di = new Di();
    $di->setShared('session', makeSessionManager());
    $di->setShared('escaper', new Escaper());

    $flash = new FlashSession();
    $flash->setDI($di);
    $flash->success('hello');

    expect($flash->has('success'))->toBeTrue()
        ->and($flash->getMessages())->toBe(['success' => ['hello']]);
});
