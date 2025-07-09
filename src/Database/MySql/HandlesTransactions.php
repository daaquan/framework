<?php

namespace Phare\Database\MySql;

use Phalcon\Mvc\Model\Transaction\Failed as TransactionFailed;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;
use Phare\Eloquent\Model;

trait HandlesTransactions
{
    protected array $activeTransactions = [];

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
        }

        $this->isInTransaction = true;
    }

    protected function beginTransactionOnSchema(string $schema): TxManager
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
        foreach ($this->activeTransactions as $txManager) {
            $txManager->get()->commit();
        }
    }

    public function undoTransactions(): void
    {
        foreach ($this->activeTransactions as $txManager) {
            $txManager->get()->rollback();
        }
    }

    public function clearTransactions(): void
    {
        $this->activeTransactions = [];
        $this->isInTransaction = false;
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
}
