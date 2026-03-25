<?php

namespace Phare\Database\MySql;

use Phalcon\Mvc\Model\Transaction\Failed as TransactionFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;
use Phare\Database\Events\TransactionBeginning;
use Phare\Database\Events\TransactionCommitted;
use Phare\Database\Events\TransactionCommitting;
use Phare\Database\Events\TransactionRolledBack;
use Phare\Eloquent\Model;

trait HandlesTransactions
{
    protected array $activeTransactions = [];

    protected array $afterCommitCallbacks = [];

    protected bool $isInTransaction = false;

    public function transaction(\Closure $operations, array $dbSchemas = []): mixed
    {
        $this->startTransactions($dbSchemas);

        try {
            $result = $operations();
            $this->finalizeTransactions();

            return $result;
        } catch (\Throwable $exception) {
            $this->undoTransactions();
            throw $exception;
        } finally {
            $this->clearTransactions();
        }
    }

    public function startTransactions(array $dbSchemas): void
    {
        foreach ($dbSchemas as $schema) {
            $this->activeTransactions[$schema] = $this->beginTransactionOnSchema($schema);
            $this->dispatchDatabaseEvent(new TransactionBeginning($schema));
        }

        $this->isInTransaction = true;
    }

    protected function beginTransactionOnSchema(string $schema): object
    {
        $txManager = new TxManager();
        $transaction = $txManager->setDbService($schema)->get();
        if (!$transaction->begin()) {
            throw new TransactionFailed("Transaction start failed on schema '{$schema}'.");
        }

        return $txManager;
    }

    public function finalizeTransactions(): void
    {
        foreach ($this->activeTransactions as $schema => $txManager) {
            $this->dispatchDatabaseEvent(new TransactionCommitting((string) $schema));
            $txManager->get()->commit();
            $this->dispatchDatabaseEvent(new TransactionCommitted((string) $schema));
        }

        foreach ($this->afterCommitCallbacks as $callback) {
            $callback();
        }

        $this->afterCommitCallbacks = [];
    }

    public function undoTransactions(): void
    {
        foreach ($this->activeTransactions as $schema => $txManager) {
            $txManager->get()->rollback();
            $this->dispatchDatabaseEvent(new TransactionRolledBack((string) $schema));
        }

        $this->afterCommitCallbacks = [];
    }

    public function clearTransactions(): void
    {
        $this->activeTransactions = [];
        $this->isInTransaction = false;
    }

    public function addCallback(\Closure $callback): void
    {
        if (!$this->isInTransaction) {
            $callback();

            return;
        }

        $this->afterCommitCallbacks[] = $callback;
    }

    public function inTransaction(): bool
    {
        return $this->isInTransaction;
    }

    public function attachModelToTransaction(Model $model): void
    {
        if (!$this->isInTransaction) {
            return;
        }

        $dbService = $model->getWriteConnectionService();
        if (array_key_exists($dbService, $this->activeTransactions)) {
            $model->setTransaction($this->activeTransactions[$dbService]->get());
        }
    }

    protected function dispatchDatabaseEvent(object $event): void
    {
        if (!property_exists($this, 'app') || !method_exists($this->app, 'bound') || !$this->app->bound('events')) {
            return;
        }

        $this->app->make('events')->dispatch($event);
    }
}
