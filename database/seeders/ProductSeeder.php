<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Executa o seeder de produtos.
     *
     * Este seeder cria produtos de exemplo na tabela posts com post_type='products'.
     * Para usar dados do CSV, substitua o array $products pelos dados do arquivo.
     */
    public function run(): void
    {
        // Dados dos produtos Oi TV - Antena+
        $products = [
            [
                'name' => 'PIX de R$150,00',
                'description' => 'Valor de R$150,00, disponibilizado por meio de transferência via PIX para a chave cadastrada pelo participante. Uma recompensa prática e imediata, que pode ser utilizada livremente conforme sua necessidade.',
                'category' => 3,
                'costPrice' => 0.00,
                'salePrice' => 150.00,
                'stock' => 100,
                'points' => '500',
                'qtd_for_user' => '10/dia',
                'delivery_time' => 'Em até 10 dias úteis',
                'term' => 'Após solicitação não será possível o cancelamento do pedido ou alteração da chave PIX informada.',
                'police' => 'Necessário ser credenciado ativo Antena+ na Oi TV e ter saldo de pontos suficientes para resgate no Clube Yellow.',
                'term2' => 'Crédito em conta será realizado em até 10 dias na chave PIX infromada no momento do resgate.',
                'image' => '/storage/products/Logo do Produto - PIX.jpg'
            ],
            [
                'name' => 'Kit com Receptor DTH e Antena',
                'description' => 'Conjunto para recepção do sinal Oi TV via satélite, composto por um receptor DTH e uma antena. Ideal para reposição ou novas instalações.\n\nImagem do produto no catálogo meramente ilustrativa.',
                'category' => 1,
                'costPrice' => 0.00,
                'salePrice' => 0.00,
                'stock' => 50,
                'points' => '500',
                'qtd_for_user' => '10/dia',
                'delivery_time' => 'Em até 15 dias úteis',
                'term' => 'Após solicitação não será possível o cancelamento do pedido ou alteração do endereço de entrega.',
                'police' => 'Necessário ser credenciado ativo Antena+ na Oi TV e ter saldo de pontos suficientes para resgate no Clube Yellow.',
                'term2' => 'Após confirmação de resgate, o produto será entregue no prazo de até 15 dias úteis no endereço informado no momento do resgate.',
                'image' => '/storage/products/Kit Receptor & Antena.jpg'
            ],
            [
                'name' => 'Camisa Oi TV - Antena+',
                'description' => 'Camisa oficial do programa Antena+, desenvolvida em tecido de malha leve e confortável. Perfeita para o dia a dia de trabalho, reforçando sua identidade como credenciado.\n\nImagem do produto no catálogo meramente ilustrativa.',
                'category' => 2,
                'costPrice' => 0.00,
                'salePrice' => 0.00,
                'stock' => 200,
                'points' => '300',
                'qtd_for_user' => '20/dia',
                'delivery_time' => 'Em até 15 dias úteis',
                'term' => 'Após solicitação não será possível o cancelamento do pedido ou alteração do endereço de entrega.',
                'police' => 'Necessário ser credenciado ativo Antena+ na Oi TV e ter saldo de pontos suficientes para resgate no Clube Yellow.',
                'term2' => 'Após confirmação de resgate, o produto será entregue no prazo de até 15 dias úteis no endereço informado no momento do resgate.',
                'image' => '/storage/products/Logo do Produto - Camisa.jpg'
            ],
            [
                'name' => 'Boné Oi TV - Antena+',
                'description' => 'Boné oficial com a marca Oi TV Antena+. Garante proteção e estilo, além de fortalecer a imagem profissional junto aos clientes.\n\nImagem do produto no catálogo meramente ilustrativa.',
                'category' => 2,
                'costPrice' => 0.00,
                'salePrice' => 0.00,
                'stock' => 200,
                'points' => '300',
                'qtd_for_user' => '20/dia',
                'delivery_time' => 'Em até 15 dias úteis',
                'term' => 'Após solicitação não será possível o cancelamento do pedido ou alteração do endereço de entrega.',
                'police' => 'Necessário ser credenciado ativo Antena+ na Oi TV e ter saldo de pontos suficientes para resgate no Clube Yellow.',
                'term2' => 'Após confirmação de resgate, o produto será entregue no prazo de até 15 dias úteis no endereço informado no momento do resgate.',
                'image' => '/storage/products/Logo do Produto - Boné.jpg'
            ],
            [
                'name' => 'Kit instalação: 2 Receptores DTH, 1 Antena  e Cabo',
                'description' => 'Pacote completo para instalação de novos clientes, incluindo dois receptores DTH, uma antena e cabo de conexão. Proporciona praticidade para atender com rapidez e eficiência.\n\nImagem do produto no catálogo meramente ilustrativa.',
                'category' => 1,
                'costPrice' => 0.00,
                'salePrice' => 0.00,
                'stock' => 50,
                'points' => '800',
                'qtd_for_user' => '10/dia',
                'delivery_time' => 'Em até 15 dias úteis',
                'term' => 'Após solicitação não será possível o cancelamento do pedido ou alteração do endereço de entrega.',
                'police' => 'Necessário ser credenciado ativo Antena+ na Oi TV e ter saldo de pontos suficientes para resgate no Clube Yellow.',
                'term2' => 'Após confirmação de resgate, o produto será entregue no prazo de até 15 dias úteis no endereço informado no momento do resgate.',
                'image' => '/storage/products/Logo do Produto - Kit Completo - 2 Receptores Antena Cabo.jpg'
            ],
            [
                'name' => 'Satlink',
                'description' => 'Medidor de sinal via satélite utilizado para alinhamento preciso de antenas. Ferramenta profissional indispensável para otimizar a qualidade do serviço prestado.\n\nImagem do produto no catálogo meramente ilustrativa.',
                'category' => 1,
                'costPrice' => 0.00,
                'salePrice' => 0.00,
                'stock' => 30,
                'points' => '1100',
                'qtd_for_user' => '10/dia',
                'delivery_time' => 'Em até 15 dias úteis',
                'term' => 'Após solicitação não será possível o cancelamento do pedido ou alteração do endereço de entrega.',
                'police' => 'Necessário ser credenciado ativo Antena+ na Oi TV e ter saldo de pontos suficientes para resgate no Clube Yellow.',
                'term2' => 'Após confirmação de resgate, o produto será entregue no prazo de até 15 dias úteis no endereço informado no momento do resgate.',
                'image' => '/storage/products/Logo do Produto - Satlink.jpg'
            ]
        ];

        foreach ($products as $product) {
            // Gerar slug único
            $slug = Str::slug($product['name']);
            $originalSlug = $slug;
            $counter = 1;

            // Verificar se o slug já existe e criar um único
            while (DB::table('posts')->where('post_name', $slug)->where('post_type', 'products')->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Preparar configurações adicionais
            $config = [
                'points' => (int)$product['points'],
                'qtd_for_user' => $product['qtd_for_user'],
                'delivery_time' => $product['delivery_time'],
                'term' => $product['term'],
                'police' => $product['police'],
                'term2' => $product['term2'],
                'image' => $product['image'],
                'inStock' => true,
            ];

            // Inserir produto na tabela posts
            DB::table('posts')->insert([
                'post_author' => '1', // ID do usuário admin
                'post_content' => $product['description'],
                'post_title' => $product['name'],
                'post_excerpt' => Str::limit($product['description'], 150),
                'post_status' => 'publish',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_password' => '',
                'post_name' => $slug,
                'to_ping' => 's',
                'pinged' => '',
                'post_content_filtered' => '',
                'post_parent' => 0,
                'guid' => $product['category'],
                'menu_order' => 0,
                'post_value1' => $product['costPrice'],
                'post_value2' => $product['salePrice'],
                'post_type' => 'products',
                'post_mime_type' => '',
                'comment_count' => $product['stock'],
                'config' => json_encode($config),
                'created_at' => now(),
                'updated_at' => now(),
                'token' => Str::random(32),
                'excluido' => 'n',
                'reg_excluido' => null,
                'deletado' => 'n',
                'reg_deletado' => null,
            ]);
        }

        $this->command->info('Produtos criados com sucesso!');
    }

    /**
     * Método para ler dados do CSV (descomente e ajuste conforme necessário)
     *
     * @param string $csvPath Caminho para o arquivo CSV
     * @return array Array com os dados dos produtos
     */
    /*
    private function readCsvData($csvPath)
    {
        $products = [];

        if (($handle = fopen($csvPath, 'r')) !== FALSE) {
            // Ler cabeçalho
            $header = fgetcsv($handle, 1000, ',');

            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                $product = array_combine($header, $data);

                // Mapear campos do CSV para estrutura esperada
                $products[] = [
                    'name' => $product['nome'] ?? '',
                    'description' => $product['descricao'] ?? '',
                    'category' => $product['categoria'] ?? '',
                    'costPrice' => floatval($product['preco_custo'] ?? 0),
                    'salePrice' => floatval($product['preco_venda'] ?? 0),
                    'stock' => intval($product['estoque'] ?? 0),
                    'unit' => $product['unidade'] ?? 'unidade',
                    'points' => intval($product['pontos'] ?? 0),
                    'image' => $product['imagem'] ?? '',
                    'rating' => floatval($product['avaliacao'] ?? 0),
                    'reviews' => intval($product['avaliacoes'] ?? 0),
                    'availability' => $product['disponibilidade'] ?? 'in_stock',
                    'terms' => $product['termos'] ?? '',
                    'validUntil' => $product['valido_ate'] ?? '',
                    'inStock' => filter_var($product['em_estoque'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'originalPrice' => floatval($product['preco_original'] ?? 0)
                ];
            }

            fclose($handle);
        }

        return $products;
    }
    */
}
