<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Classe de exportação de Relatório de Saldo de Pontos por Cliente
 */
class PointsBalancesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithColumnFormatting
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Retorna a coleção de usuários com seus saldos atuais calculados no banco
     */
    public function collection()
    {
        $orderBy = $this->filters['order_by'] ?? 'name';
        $order = $this->filters['order'] ?? 'asc';

        // Query otimizada para buscar saldo total de cada cliente em uma única passagem
        $query = DB::table('users as u')
            ->select(
                'u.id',
                'u.name',
                'u.email',
                'u.cpf',
                'u.created_at',
                DB::raw("(SELECT COALESCE(SUM(valor), 0) FROM points WHERE client_id = u.id AND excluido = 'n' AND deletado = 'n' AND status != 'cancelado') as total_balance")
            )
            ->where('u.excluido', 'n')
            ->where('u.deletado', 'n');

        // Aplicar busca global
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%")
                  ->orWhere('u.cpf', 'like', "%{$search}%");
            });
        }

        // Ordenação
        if ($orderBy === 'saldo_total') {
            $query->orderBy('total_balance', $order);
        } else {
            $query->orderBy('u.' . $orderBy, $order);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID Cliente',
            'Nome',
            'E-mail',
            'CPF/CNPJ',
            'Data Cadastro',
            'Saldo Total de Pontos',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->email ?? '—',
            $row->cpf ?? '—',
            $row->created_at ? Carbon::parse($row->created_at)->format('d/m/Y') : '—',
            (float) $row->total_balance,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '10B981']]],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT, // ID
            'D' => NumberFormat::FORMAT_TEXT, // CPF/CNPJ
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }
}
