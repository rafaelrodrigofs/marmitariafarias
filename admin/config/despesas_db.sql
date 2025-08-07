# Host: localhost  (Version 8.3.0)
# Date: 2025-03-18 09:15:16
# Generator: MySQL-Front 6.0  (Build 2.20)

#
# Structure for table "categorias_despesa"
#

CREATE TABLE `categorias_despesa` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nome_categoria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "categorias_despesa"
#

INSERT INTO `categorias_despesa` VALUES (1,'Insumos',NULL,'2024-12-10 12:38:47'),(2,'Embalagens',NULL,'2024-12-10 12:38:47'),(3,'Motoboy',NULL,'2024-12-10 12:38:47'),(4,'Fornecedores',NULL,'2024-12-10 12:38:47'),(5,'Funcionários',NULL,'2024-12-10 12:38:47'),(6,'Impostos',NULL,'2024-12-10 12:38:47'),(7,'Outros',NULL,'2024-12-10 12:38:47');

#
# Structure for table "despesas"
#

CREATE TABLE `despesas` (
  `id_despesa` int NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_despesa` date NOT NULL,
  `fornecedor` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria_id` int DEFAULT NULL,
  `forma_pagamento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprovante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `status_pagamento` tinyint NOT NULL DEFAULT '0' COMMENT '0=pendente, 1=pago, 2=vencido, 3=cancelado',
  `data_pagamento` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_despesa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Structure for table "boletos"
#

CREATE TABLE `boletos` (
  `id_boleto` int NOT NULL AUTO_INCREMENT,
  `fk_despesa_id` int NOT NULL,
  `codigo_barras` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_boleto` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=pendente, 1=pago, 2=vencido, 3=cancelado',
  `beneficiario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linha_digitavel` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `juros` decimal(10,2) DEFAULT '0.00',
  `multa` decimal(10,2) DEFAULT '0.00',
  `desconto` decimal(10,2) DEFAULT '0.00',
  `numero_nfe` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chave_nfe` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parcela` int DEFAULT NULL COMMENT 'Número da parcela (1/3, 2/3, 3/3)',
  `total_parcelas` int DEFAULT NULL COMMENT 'Total de parcelas da NF',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_boleto`),
  KEY `fk_despesa_id` (`fk_despesa_id`),
  CONSTRAINT `fk_boleto_despesa` FOREIGN KEY (`fk_despesa_id`) REFERENCES `despesas` (`id_despesa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 