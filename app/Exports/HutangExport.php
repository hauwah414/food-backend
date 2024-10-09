<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class HutangExport implements WithMultipleSheets
{
    use Exportable;
    protected $request;
    function __construct(array $request) {
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->request as $key => $value) {
            $sheets[] = new Hutang($value);
        }
        return $sheets;
    }
}