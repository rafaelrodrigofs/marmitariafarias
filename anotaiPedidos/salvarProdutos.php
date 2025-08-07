<?php
header('Content-Type: application/json');

// Inclui o arquivo de configuração do banco de dados
require_once __DIR__ . '/../admin/config/database.php';

// Recebe os dados do POST
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados JSON inválidos']);
    exit;
}

try {
    // Inicia a transação
    $pdo->beginTransaction();

    // Contadores para acompanhamento
    $resultados = [
        'categorias' => ['inseridas' => 0, 'atualizadas' => 0],
        'produtos' => ['inseridos' => 0, 'atualizados' => 0],
        'itens' => ['inseridos' => 0, 'atualizados' => 0],
        'subitens' => ['inseridos' => 0, 'atualizados' => 0]
    ];

    // Processa todos os grupos
    foreach ($data as $grupo) {
        // Se é um produto normal (is_additional = false)
        if ($grupo->is_additional === false) {
            // Verifica se a categoria já existe
            $stmtCheckCategoria = $pdo->prepare("SELECT id_category FROM p01_categories WHERE external_id_category = ?");
            $stmtCheckCategoria->execute([$grupo->id]);
            $categoriaExistente = $stmtCheckCategoria->fetch();

            if ($categoriaExistente) {
                // Atualiza categoria
                $stmtCategoria = $pdo->prepare("UPDATE p01_categories SET title_category = ? WHERE id_category = ?");
                $stmtCategoria->execute([$grupo->title, $categoriaExistente['id_category']]);
                $idCategoria = $categoriaExistente['id_category'];
                $resultados['categorias']['atualizadas']++;
            } else {
                // Insere nova categoria
                $stmtCategoria = $pdo->prepare("INSERT INTO p01_categories (title_category, external_id_category) VALUES (?, ?)");
                $stmtCategoria->execute([$grupo->title, $grupo->id]);
                $idCategoria = $pdo->lastInsertId();
                $resultados['categorias']['inseridas']++;
            }

            // Processa os produtos desta categoria
            if (isset($grupo->itens) && is_array($grupo->itens)) {
                foreach ($grupo->itens as $produto) {
                    // Verifica se o produto já existe
                    $stmtCheckProduto = $pdo->prepare("SELECT id_product FROM p02_products WHERE external_id_product = ?");
                    $stmtCheckProduto->execute([$produto->id]);
                    $produtoExistente = $stmtCheckProduto->fetch();

                    if ($produtoExistente) {
                        // Atualiza produto
                        $stmtProduto = $pdo->prepare("UPDATE p02_products SET 
                            name_product = ?, 
                            description_product = ?, 
                            img_product = ?,
                            activated = ?,
                            fk_id_category = ?
                            WHERE id_product = ?");
                        $stmtProduto->execute([
                            $produto->title,
                            $produto->description,
                            $produto->link_image,
                            $produto->out ? 0 : 1,
                            $idCategoria,
                            $produtoExistente['id_product']
                        ]);
                        $resultados['produtos']['atualizados']++;
                    } else {
                        // Insere novo produto
                        $stmtProduto = $pdo->prepare("INSERT INTO p02_products 
                            (name_product, description_product, img_product, external_id_product, activated, fk_id_category) 
                            VALUES (?, ?, ?, ?, ?, ?)");
                        $stmtProduto->execute([
                            $produto->title,
                            $produto->description,
                            $produto->link_image,
                            $produto->id,
                            $produto->out ? 0 : 1,
                            $idCategoria
                        ]);
                        $resultados['produtos']['inseridos']++;
                    }

                    // Processa os itens de acompanhamento (next_steps)
                    if (isset($produto->next_steps) && is_array($produto->next_steps)) {
                        foreach ($produto->next_steps as $acompanhamento) {
                            // Verifica se o item já existe
                            $stmtCheckItem = $pdo->prepare("SELECT id_item FROM p03_items WHERE external_id_item = ?");
                            $stmtCheckItem->execute([$acompanhamento->category_id]);
                            $itemExistente = $stmtCheckItem->fetch();

                            if ($itemExistente) {
                                // Atualiza item
                                $stmtItem = $pdo->prepare("UPDATE p03_items SET name_item = ? WHERE id_item = ?");
                                $stmtItem->execute([$acompanhamento->category_title, $itemExistente['id_item']]);
                                $resultados['itens']['atualizados']++;
                            } else {
                                // Insere novo item
                                $stmtItem = $pdo->prepare("INSERT INTO p03_items (name_item, external_id_item) VALUES (?, ?)");
                                $stmtItem->execute([$acompanhamento->category_title, $acompanhamento->category_id]);
                                $resultados['itens']['inseridos']++;
                            }
                        }
                    }
                }
            }
        }
        
        // Se é um grupo de adicionais (is_additional = true)
        if ($grupo->is_additional === true) {
            // Verifica se o item já existe
            $stmtCheckItem = $pdo->prepare("SELECT id_item FROM p03_items WHERE external_id_item = ?");
            $stmtCheckItem->execute([$grupo->id]);
            $itemExistente = $stmtCheckItem->fetch();

            if ($itemExistente) {
                // Atualiza item
                $stmtItem = $pdo->prepare("UPDATE p03_items SET name_item = ? WHERE id_item = ?");
                $stmtItem->execute([$grupo->title, $itemExistente['id_item']]);
                $idItem = $itemExistente['id_item'];
                $resultados['itens']['atualizados']++;
            } else {
                // Insere novo item
                $stmtItem = $pdo->prepare("INSERT INTO p03_items (name_item, external_id_item) VALUES (?, ?)");
                $stmtItem->execute([$grupo->title, $grupo->id]);
                $idItem = $pdo->lastInsertId();
                $resultados['itens']['inseridos']++;
            }

            // Processa os subitens
            if (isset($grupo->itens) && is_array($grupo->itens)) {
                foreach ($grupo->itens as $subitem) {
                    // Verifica se o subitem já existe
                    $stmtCheckSubitem = $pdo->prepare("SELECT id_subitem FROM p04_subitems WHERE external_id_subitem = ?");
                    $stmtCheckSubitem->execute([$subitem->id]);
                    $subitemExistente = $stmtCheckSubitem->fetch();

                    if ($subitemExistente) {
                        // Atualiza subitem
                        $stmtSubitem = $pdo->prepare("UPDATE p04_subitems SET 
                            name_subitem = ?, 
                            fk_id_items = ? 
                            WHERE id_subitem = ?");
                        $stmtSubitem->execute([
                            $subitem->title,
                            $idItem,
                            $subitemExistente['id_subitem']
                        ]);
                        $resultados['subitens']['atualizados']++;
                    } else {
                        // Insere novo subitem
                        $stmtSubitem = $pdo->prepare("INSERT INTO p04_subitems 
                            (name_subitem, external_id_subitem, fk_id_items) 
                            VALUES (?, ?, ?)");
                        $stmtSubitem->execute([
                            $subitem->title,
                            $subitem->id,
                            $idItem
                        ]);
                        $resultados['subitens']['inseridos']++;
                    }
                }
            }
        }
    }

    // Commit da transação
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'resultados' => $resultados
    ]);

} catch (Exception $e) {
    // Em caso de erro, faz rollback
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao salvar no banco de dados',
        'details' => $e->getMessage()
    ]);
}
?> 