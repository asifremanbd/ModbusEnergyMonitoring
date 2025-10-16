<?php

namespace App\Traits;

trait PreservesNavigationState
{
    protected function getTableStateSessionKey(): string
    {
        return static::class . '_table_state_' . $this->getStateIdentifier();
    }

    abstract protected function getStateIdentifier(): string;

    protected function restoreTableState(): void
    {
        $sessionKey = $this->getTableStateSessionKey();
        $tableState = session($sessionKey);
        
        if ($tableState && isset($tableState['timestamp'])) {
            // Only restore state if it's less than 1 hour old
            $stateAge = now()->timestamp - $tableState['timestamp'];
            if ($stateAge > 3600) {
                session()->forget($sessionKey);
                return;
            }
            
            // Restore filters
            if (isset($tableState['filters'])) {
                foreach ($tableState['filters'] as $filter => $value) {
                    $this->tableFilters[$filter] = $value;
                }
            }
            
            // Restore search
            if (isset($tableState['search'])) {
                $this->tableSearch = $tableState['search'];
            }
            
            // Restore sorting
            if (isset($tableState['sort'])) {
                $this->tableSortColumn = $tableState['sort']['column'] ?? null;
                $this->tableSortDirection = $tableState['sort']['direction'] ?? null;
            }
        }
    }

    protected function saveTableState(): void
    {
        $sessionKey = $this->getTableStateSessionKey();
        
        session([
            $sessionKey => [
                'filters' => $this->tableFilters ?? [],
                'search' => $this->tableSearch ?? '',
                'sort' => [
                    'column' => $this->tableSortColumn,
                    'direction' => $this->tableSortDirection,
                ],
                'timestamp' => now()->timestamp
            ]
        ]);
    }

    public function dehydrate(): void
    {
        $this->saveTableState();
    }

    protected function navigateWithStatePreservation(string $url): void
    {
        $this->saveTableState();
        $this->redirect($url);
    }
}