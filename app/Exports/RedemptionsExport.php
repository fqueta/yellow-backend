<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;

use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Classe de exportação de Pedidos de Resgate (Pedidos)
 */
class RedemptionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithEvents
{
    protected $filters;

    // 🔥 Armazena os telefones para aplicar máscara depois
    protected $phones = [];

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = DB::table('redemptions as r')
            ->select(
                'r.id',
                'r.points_used',
                'r.status',
                'r.created_at',
                'u.name as user_name',
                'u.email as user_email',
                'u.cpf as user_cpf',
                'u.config as user_config',
                'p.post_title as product_name',
                'c.name as product_category'
            )
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('posts as p', 'p.ID', '=', 'r.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.guid')
            ->where('r.excluido', 'n');

        // Filtros
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('r.status', $this->filters['status']);
        }

        if (!empty($this->filters['autor'])) {
            $query->where('r.autor', $this->filters['autor']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('u.name', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%")
                  ->orWhere('u.cpf', 'like', "%{$search}%")
                  ->orWhere('p.post_title', 'like', "%{$search}%")
                  ->orWhere('r.id', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['dateFrom'])) {
            $query->where('r.created_at', '>=', Carbon::parse($this->filters['dateFrom'])->startOfDay());
        }

        if (!empty($this->filters['dateTo'])) {
            $query->where('r.created_at', '<=', Carbon::parse($this->filters['dateTo'])->endOfDay());
        }

        if (!empty($this->filters['category']) && $this->filters['category'] !== 'all') {
            $categoryName = $this->filters['category'];
            $categoryIds = DB::table('categories')->where('name', $categoryName)->pluck('id');
            
            if ($categoryIds->isNotEmpty()) {
                $query->whereIn('p.guid', $categoryIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->orderBy('r.created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Cliente',
            'CPF',
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
        $config = json_decode($row->user_config, true) ?? [];
        $phone = $config['celular'] ?? $config['phone'] ?? $config['telefone'] ?? '';

        // 🔥 Apenas números
        $phoneDigits = preg_replace('/\D/', '', $phone);

        // 🔥 Guarda para formatar depois
        $this->phones[] = $phoneDigits;

        return [
            $row->id,
            $row->user_name,
            $this->formatCpf($row->user_cpf),
            $row->user_email,
            is_numeric($phoneDigits) ? (int) $phoneDigits : null,
            $row->product_name,
            $row->product_category,
            (float) $row->points_used,
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

    /**
     * Formata CPF para exibição no arquivo exportado.
     */
    protected function formatCpf($cpf)
    {
        $digits = preg_replace('/\D/', '', (string) $cpf);

        if (strlen($digits) !== 11) {
            return $cpf ?: null;
        }

        return substr($digits, 0, 3) . '.' .
            substr($digits, 3, 3) . '.' .
            substr($digits, 6, 3) . '-' .
            substr($digits, 9, 2);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']]
            ],
        ];
    }

    // 🔥 Aqui acontece a mágica
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                foreach ($this->phones as $index => $phone) {

                    $row = $index + 2; // linha 1 = cabeçalho
                    $cell = 'E' . $row;

                    if (strlen($phone) === 11) {
                        // Celular
                        $sheet->getStyle($cell)
                            ->getNumberFormat()
                            ->setFormatCode('(00) 00000-0000');

                    } elseif (strlen($phone) === 10) {
                        // Fixo
                        $sheet->getStyle($cell)
                            ->getNumberFormat()
                            ->setFormatCode('(00) 0000-0000');

                    } else {
                        // fallback seguro
                        $sheet->setCellValueExplicit(
                            $cell,
                            $phone,
                            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                        );
                    }
                }
            },
        ];
    }
}
