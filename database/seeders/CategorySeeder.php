<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Categorias da entidade PRODUTOS
        $categoriasProdutos = [
            'Lubrificantes',
            'Filtros',
            'Pneus',
            'Elétrica',
            'Freios',
            'Suspensão',
            'Motor',
            'Diversos',
        ];

        // Categorias da entidade SERVIÇOS
        $categoriasServicos = [
            'Manutenção de 50h',
            'Manutenção de 100h',
            'Manutenção de 200h',
            'Manutenção de 500h',
            'Manutenção de 1000h',
            'CVA',
            'Inspeção de aeronavegabilidade',
            'Inspeção pré-compra',
            'Pane',
            'Outros',
        ];

        // 🔹 Mantém apenas as categorias definidas
        DB::table('categories')
            ->where('entidade', 'produtos')
            ->whereNotIn('name', $categoriasProdutos)
            ->delete();

        DB::table('categories')
            ->where('entidade', 'servicos')
            ->whereNotIn('name', $categoriasServicos)
            ->delete();

        // 🔹 Recria/atualiza categorias de PRODUTOS
        foreach ($categoriasProdutos as $nome) {
            DB::table('categories')->updateOrInsert(
                [
                    'name'     => $nome,
                    'entidade' => 'produtos',
                ],
                [
                    'description' => "Categoria de produto: {$nome}",
                    'parent_id'   => null,
                    'active'      => true,
                    'entidade'    => 'produtos',
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }

        // 🔹 Recria/atualiza categorias de SERVIÇOS
        foreach ($categoriasServicos as $nome) {
            DB::table('categories')->updateOrInsert(
                [
                    'name'     => $nome,
                    'entidade' => 'servicos',
                ],
                [
                    'description' => "Categoria de serviço: {$nome}",
                    'parent_id'   => null,
                    'active'      => true,
                    'entidade'    => 'servicos',
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }
    }
}
