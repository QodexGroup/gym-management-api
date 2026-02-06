<?php

namespace App\Dtos\Core;

class ExpenseReportDto
{
    private int $accountId;
    private string $dateFrom;
    private string $dateTo;
    private ?string $exportType = null;
    private ?string $periodLabel = null;

    public function getAccountId(): int
    {
        return $this->accountId;
    }

    public function setAccountId(int $accountId): self
    {
        $this->accountId = $accountId;
        return $this;
    }

    public function getDateFrom(): string
    {
        return $this->dateFrom;
    }

    public function setDateFrom(string $dateFrom): self
    {
        $this->dateFrom = $dateFrom;
        return $this;
    }

    public function getDateTo(): string
    {
        return $this->dateTo;
    }

    public function setDateTo(string $dateTo): self
    {
        $this->dateTo = $dateTo;
        return $this;
    }

    public function getExportType(): ?string
    {
        return $this->exportType;
    }

    public function setExportType(?string $exportType): self
    {
        $this->exportType = $exportType;
        return $this;
    }

    public function getPeriodLabel(): ?string
    {
        return $this->periodLabel;
    }

    public function setPeriodLabel(?string $periodLabel): self
    {
        $this->periodLabel = $periodLabel;
        return $this;
    }
}
