<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Classe de exportação de Pedidos de Resgate (Pedidos)
 */
class RedemptionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = DB::table('redims as r')
            ->select(
                'r.id',
                'r.config',
                'r.status',
                'r.created_at',
                'u.name as user_name',
                'u.email as user_email',
                'u.cpf as user_cpf',
                'u.phone as user_phone',
                'p.nome as product_name',
                'c.nome as product_category'
            )
            ->leftJoin('users as u', 'u.id', '=', 'r.client_id')
            ->leftJoin('products as p', 'p.id', '=', 'r.produto_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.categoria_id')
            ->where('r.excluido', 'n');

        // Filtros
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('r.status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%")
                  ->orWhere('p.nome', 'like', "%{$search}%")
                  ->orWhere('r.id', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['dateFrom'])) {
            $query->where('r.created_at', '>=', Carbon::parse($this->filters['dateFrom'])->startOfDay());
        }

        if (!empty($this->filters['dateTo'])) {
            $query->where('r.created_at', '<=', Carbon::parse($this->filters['dateTo'])->endOfDay());
        }

        return $query->orderBy('r.created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'E-mail',
            'Telefone',
            'Produto',
            'Categoria',
            'Pontos Usados',
            'Data do Pedido',
            'Status',
        ];
    }

    public function map($row): array
    {
        $config = json_decode($row->config, true) ?? [];
        $pointsUsed = $config['pontos_usados'] ?? 0;
        
        return [
            $row->id,
            $row->user_name,
            $row->user_email,
            $row->user_phone,
            $row->product_name,
            $row->product_category,
            (float) $pointsUsed,
            Carbon::parse($row->created_at)->format('d/m/Y H:i'),
            $this->formatStatus($row->status),
        ];
    }

    protected function formatStatus($status)
    {
        $labels = [
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'confirmed' => 'Confirmado',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
            'refunded' => 'Estornado',
        ];
        return $labels[$status] ?? $status;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']]],
        ];
    }
}
