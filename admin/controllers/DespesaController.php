<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/login.php');
    exit();
}

class DespesaController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function criar($dados) {
        try {
            // Iniciar transação
            $this->conn->begin_transaction();
            
            // Inserir despesa
            $sql = "INSERT INTO despesas (descricao, valor, data_despesa, fornecedor, categoria_id, 
                    forma_pagamento, status_pagamento, observacao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sdssisiss", 
                $dados['descricao'],
                $dados['valor'],
                $dados['data_despesa'],
                $dados['fornecedor'],
                $dados['categoria_id'],
                $dados['forma_pagamento'],
                $dados['status_pagamento'],
                $dados['observacao']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao inserir despesa: " . $stmt->error);
            }
            
            $id_despesa = $this->conn->insert_id;
            
            // Se for boleto, inserir informações do boleto
            if ($dados['forma_pagamento'] === 'boleto') {
                $sql_boleto = "INSERT INTO boletos (fk_despesa_id, codigo_barras, valor_boleto, 
                              data_vencimento, parcela, total_parcelas) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                              
                $stmt_boleto = $this->conn->prepare($sql_boleto);
                $stmt_boleto->bind_param("isdsii",
                    $id_despesa,
                    $dados['codigo_barras'],
                    $dados['valor'],
                    $dados['data_vencimento'],
                    $dados['parcela'],
                    $dados['total_parcelas']
                );
                
                if (!$stmt_boleto->execute()) {
                    throw new Exception("Erro ao inserir boleto: " . $stmt_boleto->error);
                }
            }
            
            // Upload do comprovante se existir
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === 0) {
                $ext = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                $novo_nome = "comprovante_" . $id_despesa . "." . $ext;
                $diretorio = "../uploads/comprovantes/";
                
                if (!is_dir($diretorio)) {
                    mkdir($diretorio, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $diretorio . $novo_nome)) {
                    $sql_update = "UPDATE despesas SET comprovante = ? WHERE id_despesa = ?";
                    $stmt_update = $this->conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $novo_nome, $id_despesa);
                    
                    if (!$stmt_update->execute()) {
                        throw new Exception("Erro ao atualizar comprovante: " . $stmt_update->error);
                    }
                }
            }
            
            // Commit da transação
            $this->conn->commit();
            
            return [
                'status' => 'success',
                'message' => 'Despesa cadastrada com sucesso!',
                'id_despesa' => $id_despesa
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function atualizar($id, $dados) {
        try {
            $this->conn->begin_transaction();
            
            $sql = "UPDATE despesas SET 
                    descricao = ?,
                    valor = ?,
                    data_despesa = ?,
                    fornecedor = ?,
                    categoria_id = ?,
                    forma_pagamento = ?,
                    status_pagamento = ?,
                    observacao = ?
                    WHERE id_despesa = ?";
                    
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sdssisissi",
                $dados['descricao'],
                $dados['valor'],
                $dados['data_despesa'],
                $dados['fornecedor'],
                $dados['categoria_id'],
                $dados['forma_pagamento'],
                $dados['status_pagamento'],
                $dados['observacao'],
                $id
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao atualizar despesa: " . $stmt->error);
            }
            
            // Atualizar boleto se existir
            if ($dados['forma_pagamento'] === 'boleto') {
                $sql_boleto = "UPDATE boletos SET 
                              codigo_barras = ?,
                              valor_boleto = ?,
                              data_vencimento = ?,
                              parcela = ?,
                              total_parcelas = ?
                              WHERE fk_despesa_id = ?";
                              
                $stmt_boleto = $this->conn->prepare($sql_boleto);
                $stmt_boleto->bind_param("sdsiis",
                    $dados['codigo_barras'],
                    $dados['valor'],
                    $dados['data_vencimento'],
                    $dados['parcela'],
                    $dados['total_parcelas'],
                    $id
                );
                
                if (!$stmt_boleto->execute()) {
                    throw new Exception("Erro ao atualizar boleto: " . $stmt_boleto->error);
                }
            }
            
            // Atualizar comprovante se enviado
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === 0) {
                $ext = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                $novo_nome = "comprovante_" . $id . "." . $ext;
                $diretorio = "../uploads/comprovantes/";
                
                // Remover comprovante antigo se existir
                $sql_select = "SELECT comprovante FROM despesas WHERE id_despesa = ?";
                $stmt_select = $this->conn->prepare($sql_select);
                $stmt_select->bind_param("i", $id);
                $stmt_select->execute();
                $result = $stmt_select->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['comprovante'] && file_exists($diretorio . $row['comprovante'])) {
                    unlink($diretorio . $row['comprovante']);
                }
                
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $diretorio . $novo_nome)) {
                    $sql_update = "UPDATE despesas SET comprovante = ? WHERE id_despesa = ?";
                    $stmt_update = $this->conn->prepare($sql_update);
                    $stmt_update->bind_param("si", $novo_nome, $id);
                    
                    if (!$stmt_update->execute()) {
                        throw new Exception("Erro ao atualizar comprovante: " . $stmt_update->error);
                    }
                }
            }
            
            $this->conn->commit();
            
            return [
                'status' => 'success',
                'message' => 'Despesa atualizada com sucesso!'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function excluir($id) {
        try {
            $this->conn->begin_transaction();
            
            // Remover comprovante se existir
            $sql_select = "SELECT comprovante FROM despesas WHERE id_despesa = ?";
            $stmt_select = $this->conn->prepare($sql_select);
            $stmt_select->bind_param("i", $id);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['comprovante']) {
                $diretorio = "../uploads/comprovantes/";
                if (file_exists($diretorio . $row['comprovante'])) {
                    unlink($diretorio . $row['comprovante']);
                }
            }
            
            // Excluir boleto se existir (cascade)
            $sql = "DELETE FROM despesas WHERE id_despesa = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if (!$stmt->execute()) {
                throw new Exception("Erro ao excluir despesa: " . $stmt->error);
            }
            
            $this->conn->commit();
            
            return [
                'status' => 'success',
                'message' => 'Despesa excluída com sucesso!'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function buscarPorId($id) {
        $sql = "SELECT d.*, b.* 
                FROM despesas d 
                LEFT JOIN boletos b ON d.id_despesa = b.fk_despesa_id 
                WHERE d.id_despesa = ?";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function listar($filtros = []) {
        $where = [];
        $params = [];
        $types = "";
        
        $sql = "SELECT d.*, c.nome_categoria 
                FROM despesas d 
                LEFT JOIN categorias_despesa c ON d.categoria_id = c.id_categoria";
        
        if (!empty($filtros['data_inicio'])) {
            $where[] = "d.data_despesa >= ?";
            $params[] = $filtros['data_inicio'];
            $types .= "s";
        }
        
        if (!empty($filtros['data_fim'])) {
            $where[] = "d.data_despesa <= ?";
            $params[] = $filtros['data_fim'];
            $types .= "s";
        }
        
        if (!empty($filtros['categoria_id'])) {
            $where[] = "d.categoria_id = ?";
            $params[] = $filtros['categoria_id'];
            $types .= "i";
        }
        
        if (isset($filtros['status']) && $filtros['status'] !== '') {
            $where[] = "d.status_pagamento = ?";
            $params[] = $filtros['status'];
            $types .= "i";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY d.data_despesa DESC";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Instanciar o controlador
$despesaController = new DespesaController($conn);

// Tratar as requisições
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

switch ($acao) {
    case 'criar':
        $resultado = $despesaController->criar($_POST);
        break;
        
    case 'atualizar':
        $id = $_POST['id_despesa'] ?? 0;
        $resultado = $despesaController->atualizar($id, $_POST);
        break;
        
    case 'excluir':
        $id = $_GET['id'] ?? 0;
        $resultado = $despesaController->excluir($id);
        break;
        
    case 'buscar':
        $id = $_GET['id'] ?? 0;
        $resultado = $despesaController->buscarPorId($id);
        break;
        
    case 'listar':
        $filtros = [
            'data_inicio' => $_GET['data_inicio'] ?? null,
            'data_fim' => $_GET['data_fim'] ?? null,
            'categoria_id' => $_GET['categoria_id'] ?? null,
            'status' => $_GET['status'] ?? null
        ];
        $resultado = $despesaController->listar($filtros);
        break;
        
    default:
        $resultado = [
            'status' => 'error',
            'message' => 'Ação inválida'
        ];
}

// Retornar resultado em JSON
header('Content-Type: application/json');
echo json_encode($resultado); 