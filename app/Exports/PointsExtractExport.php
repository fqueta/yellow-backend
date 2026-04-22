<?php

namespace App\Exports;

use App\Models\Point;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Classe de exportação de extratos de pontos
 * 
 * DESIGN PATTERN: Emplega princípios de Clean Code e Separaration of Concerns.
 * Utiliza o banco de dados (MariaDB Window Functions) para cálculos complexos,
 * evitando o problema de performance N+1.
 */
class PointsExtractExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Retorna a coleção de dados otimizada
     * 
     * BEST PRACTICE: O cálculo do saldo (balance_before/after) é feito via 
     * Window Function (SUM OVER) no banco de dados. Isso permite processar 
     * milhares de linhas em milissegundos.
     */
    public function collection()
    {
        // 1. Definir a query base com o cálculo de histórico (Window Function)
        // Usamos withTrashed() para evitar que o Eloquent adicione automaticamente
        // 'points.deleted_at is null' de forma que quebre o SQL da subquery.
        $innerQuery = Point::withTrashed()
            ->from('points as p')
            ->select(
                'p.id',
                'p.client_id',
                'p.valor',
                'p.tipo',
                'p.status',
                'p.description',
                'p.pedido_id',
                'p.origem',
                'p.data_expiracao',
                'p.created_at',
                'p.excluido',
                'p.deletado',
                'c.name as user_name',
                'c.email as user_email',
                'c.cpf as user_cpf'
            )
            ->leftJoin('users as c', 'c.id', '=', 'p.client_id')
            ->where('p.excluido', '=', 'n')
            ->where('p.deletado', '=', 'n')
            ->where('p.status', '!=', 'cancelado')
            ->selectRaw("
                SUM(p.valor) OVER (PARTITION BY p.client_id ORDER BY p.created_at ASC, p.id ASC) - p.valor as running_balance_before,
                SUM(p.valor) OVER (PARTITION BY p.client_id ORDER BY p.created_at ASC, p.id ASC) as running_balance_after
            ");

        // 2. Criar a query final que aplica os filtros do usuário sobre o histórico calculado
        // IMPORTANTE: Usamos withTrashed() novamente aqui para evitar o erro de coluna na outer query
        $query = Point::withTrashed()
            ->from(DB::raw("({$innerQuery->toSql()}) as history"))
            ->setBindings($innerQuery->getBindings());

        // 3. Aplicar filtros dinâmicos (ID, Tipo, Busca, Datas)
        $this->applyFilters($query);

        // 4. Ordenação final
        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        return $query->get();
    }

    /**
     * Aplica os filtros na query
     */
    protected function applyFilters($query)
    {
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('user_cpf', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('pedido_id', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['type'])) {
            $type = $this->filters['type'];
            if ($type === 'expired' || $type === 'expiracao') {
                $query->where(function($q) {
                    $q->where('tipo', 'expired')->orWhere('status', 'expirado');
                });
            } else {
                $query->where('tipo', $type);
            }
        }

        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['dateFrom'])) {
            $query->where('created_at', '>=', Carbon::parse($this->filters['dateFrom'])->startOfDay());
        }

        if (!empty($this->filters['dateTo'])) {
            $query->where('created_at', '<=', Carbon::parse($this->filters['dateTo'])->endOfDay());
        }

        if (!empty($this->filters['user_id'])) {
            $query->where('client_id', $this->filters['user_id']);
        }
    }

    /**
     * Cabeçalhos da tabela
     */
    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'CPF',
            'Email',
            'Tipo',
            'Pontos',
            'Descrição',
            'Saldo Anterior',
            'Saldo Atual',
            'Data',
            'Expiração',
        ];
    }

    /**
     * Mapeamento de cada linha
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->user_name ?? 'N/A',
            $row->user_cpf ?? 'N/A',
            $row->user_email ?? 'N/A',
            $this->formatType($row),
            (float) $row->valor,
            $row->description,
            (float) $row->running_balance_before,
            (float) $row->running_balance_after,
            Carbon::parse($row->created_at)->format('d/MM/Y H:i'),
            $row->data_expiracao ? Carbon::parse($row->data_expiracao)->format('d/MM/Y') : '—',
        ];
    }

    /**
     * Formata o tipo para exibição legível
     */
    protected function formatType($row)
    {
        $types = [
            'credito' => 'Crédito',
            'debito' => 'Resgate',
            'expired' => 'Expirado',
            'bonus' => 'Bônus',
            'adjustment' => 'Ajuste',
            'refund' => 'Estorno',
        ];

        if ($row->origem === 'migracao_legado') return 'Migração';
        if ($row->status === 'expirado') return 'Expirado';
        
        return $types[$row->tipo] ?? ucfirst($row->tipo);
    }

    /**
     * Estilização da worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']]],
        ];
    }
}
