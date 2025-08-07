<?php

class ApiAnotai {
    private $token;
    private $baseUrl = 'https://api-parceiros.anota.ai/partnerauth';

    public function __construct($token) {
        $this->token = $token;
    }

    /**
     * Configuração padrão para as requisições HTTP
     */
    private function getHeaders() {
        return [
            'Authorization: ' . $this->token,
            'Content-Type: application/json'
        ];
    }

    /**
     * Realiza uma requisição HTTP
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        $curl = curl_init();
        $url = $this->baseUrl . $endpoint;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
        ];

        if ($data && $method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new Exception("Erro na requisição: " . $err);
        }

        return json_decode($response, true);
    }

    /**
     * Lista todos os pedidos do dia
     */
    public function listarPedidos($pagina = 1, $excluirIfood = true) {
        $queryParams = http_build_query([
            'excludeIfood' => $excluirIfood ? '1' : '0',
            'groupOrdersByTable' => '1',
            'currentpage' => $pagina
        ]);

        return $this->request("/ping/list?" . $queryParams);
    }

    /**
     * Busca informações detalhadas de um pedido específico
     */
    public function buscarPedido($orderId) {
        return $this->request("/ping/get/" . $orderId);
    }

    /**
     * Lista pedidos por status específico
     */
    public function listarPedidosPorStatus($status) {
        $queryParams = [];
        
        switch ($status) {
            case 0: // Em análise
                $queryParams['inAnalysis'] = 'true';
                break;
            case 1: // Em produção
                $queryParams['inProduction'] = 'true';
                break;
            case 3: // Finalizado
                $queryParams['inFinished'] = 'true';
                break;
        }

        $queryString = http_build_query($queryParams);
        return $this->request("/ping/list?" . $queryString);
    }
}

// Exemplo de uso:
/*
$token = 'seu_token_aqui';
$api = new ApiAnotai($token);

try {
    // Listar todos os pedidos
    $pedidos = $api->listarPedidos();
    
    // Buscar um pedido específico
    $pedido = $api->buscarPedido('id_do_pedido');
    
    // Listar pedidos em análise
    $pedidosEmAnalise = $api->listarPedidosPorStatus(0);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
*/
