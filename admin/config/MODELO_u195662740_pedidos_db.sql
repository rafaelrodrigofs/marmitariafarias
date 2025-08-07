# Host: localhost  (Version 8.3.0)
# Date: 2025-03-18 09:15:16
# Generator: MySQL-Front 6.0  (Build 2.20)


#
# Structure for table "acomp"
#

CREATE TABLE `acomp` (
  `id_acomp` int NOT NULL AUTO_INCREMENT,
  `nome_acomp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_acomp`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "acomp"
#

INSERT INTO `acomp` VALUES (1,'Feijão'),(2,'Monte sua Marmita'),(3,'Carne'),(4,'Adicional Ovo'),(5,'Adicional Salada'),(7,'Adicional Carne 1\r\n'),(8,'Massas'),(9,'Tamanhos Caldos'),(10,'Adicional Banana'),(11,'Precisa de Talher ?\r\n'),(12,'Tamanho Feijão');

#
# Structure for table "categoria"
#

CREATE TABLE `categoria` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nome_categoria` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_categoria`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "categoria"
#

INSERT INTO `categoria` VALUES (1,'Marmitas'),(2,'Feijoada'),(3,'Bebidas'),(4,'Adicional Carne'),(5,'Adicionais Diversos\r\n'),(12,'Massas'),(13,'Caldos'),(14,'Combos');

#
# Structure for table "categorias_despesa"
#

CREATE TABLE `categorias_despesa` (
  `id_categoria` int NOT NULL AUTO_INCREMENT,
  `nome_categoria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_categoria`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "categorias_despesa"
#

INSERT INTO `categorias_despesa` VALUES (1,'Insumos',NULL,'2024-12-10 12:38:47'),(2,'Embalagens',NULL,'2024-12-10 12:38:47'),(3,'Motoboy',NULL,'2024-12-10 12:38:47'),(4,'Fornecedores',NULL,'2024-12-10 12:38:47'),(5,'Funcionários',NULL,'2024-12-10 12:38:47'),(6,'Impostos',NULL,'2024-12-10 12:38:47'),(7,'Outros',NULL,'2024-12-10 12:38:47');

#
# Structure for table "cliente_bairro"
#

CREATE TABLE `cliente_bairro` (
  `id_bairro` int NOT NULL AUTO_INCREMENT,
  `nome_bairro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_taxa` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_bairro`)
) ENGINE=MyISAM AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "cliente_bairro"
#

INSERT INTO `cliente_bairro` VALUES (1,'Retirada Local',0.00),(2,'Alphaville',10.00),(3,'Alto Tarumã',5.00),(4,'Atuba - Colombo',8.00),(5,'Atuba - Curitiba',10.00),(6,'Atuba - Pinhais',5.00),(7,'Bairro Alto',10.00),(8,'Centro Pinhais',10.00),(9,'Emiliano Perneta',8.00),(10,'Estância Pinhais',12.00),(11,'Graciosa - Canguiri',10.00),(12,'_Harpia/Leme',0.00),(13,'Jd. Claudia',3.00),(14,'_Kalay do Brasil',0.00),(15,'Maria Antonieta',12.00),(16,'Pineville',8.00),(17,'Planta Karla',12.00),(18,'Vargem Grande',12.00),(19,'Vila Amélia',12.00),(20,'Vila Zumbi',8.00),(21,'Weissopolis',15.00);

#
# Structure for table "cliente_entrega"
#

CREATE TABLE `cliente_entrega` (
  `id_entrega` int NOT NULL AUTO_INCREMENT,
  `nome_entrega` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_entrega` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fk_Cliente_id_cliente` int DEFAULT NULL,
  `fk_Bairro_id_bairro` int DEFAULT NULL,
  `data_insert` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do cadastro do endereço no sistema',
  PRIMARY KEY (`id_entrega`),
  UNIQUE KEY `unique_cliente_endereco` (`fk_Cliente_id_cliente`,`nome_entrega`(100),`numero_entrega`),
  KEY `fk_Cliente_id_cliente` (`fk_Cliente_id_cliente`),
  KEY `fk_Bairro_id_bairro` (`fk_Bairro_id_bairro`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "cliente_entrega"
#


#
# Structure for table "clientes"
#

CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `nome_cliente` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone_cliente` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fk_empresa_id` int DEFAULT NULL,
  `tipo_cliente` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=pessoa_fisica, 1=funcionario',
  `data_insert` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do cadastro do cliente no sistema',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `idx_telefone_cliente` (`telefone_cliente`,`id_cliente`),
  KEY `idx_empresa_cliente` (`fk_empresa_id`),
  KEY `idx_tipo_cliente` (`tipo_cliente`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "clientes"
#


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
# Data for table "despesas"
#

INSERT INTO `despesas` VALUES (1,'TESTE',12.00,'2024-12-11','SE',5,NULL,NULL,NULL,0,NULL,'2024-12-11 05:06:17','2024-12-11 05:06:17'),(2,'kdjsflksaçjdf',23213.00,'2025-02-16','rasdfsdfdsa',2,NULL,NULL,NULL,0,NULL,'2025-02-16 15:46:40','2025-02-16 15:46:40');

#
# Structure for table "device_tokens"
#

CREATE TABLE `device_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=4811 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "device_tokens"
#

INSERT INTO `device_tokens` VALUES (45,'fJUVBZ5QNzVZTsQ3LMvocU:APA91bE74lLBIoGGY0nYHdWh7PMtaIDCyMXBGsOBzl33AnsXureLmrb781Kt7B52RlwNKGM8jSmDdG_2hepvyWwEq31zaH0wwa0fdHaOy-jmlTCqvQXWW3g','2025-01-16 22:53:01'),(53,'eVTVXt_rR4LctMBQYBlI1B:APA91bFnS3Kvs4Opz2dodWZD4CIYm_1B318gHCroFVbkyU-tSWCDhExs3QfzdDLnYkOXOFy6ZeQXCC51D-Qb_OFHzP7dSl9dLwLs6o_62cHuj0HJheP4jjU','2025-01-17 13:46:50'),(158,'desabu1atycus1-DYH8tR2:APA91bG97wBW1zJ5L5sdfG8E6-NpiuXE8QapQV_sx8VTTnV5UGr5_KmUsU-wf90lFhoV7huUGr1Gh9UAPVKnNwDvIsJ_oEQksgE8bPrRZkWXidNCZY7mo74','2025-01-18 16:16:56'),(160,'fJ0n25Johi00ZvZ6vdEl4E:APA91bHvrO1v0WyftcyGtDZ1rsE7ye691ZQtHh-0HEIktLP3lYD66vDv1HlqPOQ1hOFj3QtnhdXP6l7nTOn9X_GMSdixdBNaVLp1Wlh_3x5g2uSD2LhbXYA','2025-01-20 11:26:04'),(482,'fJUVBZ5QNzVZTsQ3LMvocU:APA91bGX-lReukLxIspolHDihvhwzKPn1XMcq3Ja2nA2g1psBhNogAI6z5A6v_sVU-j0SV1HHA2jsee5Zh_gXa2FNWBtEgXgDFG8wBQmFFkw_5kVgrQsqBo','2025-01-21 16:25:07'),(483,'fJUVBZ5QNzVZTsQ3LMvocU:APA91bHO0d6BmS-gukjq5iWoW4t_Z-OfENjsMt_XZ0RlvkLpH_iGKK6gFJrqOu-WJM_8qfVzbcoqGn5a7Wp7XuO6CvOjVL79A6RYF78b1PNfaF019pORRCw','2025-01-21 16:25:07'),(582,'eVTVXt_rR4LctMBQYBlI1B:APA91bHTijZylBLTjshM7S_AYcAETRtawW8t9Or27pkQ9j_CXOLuMMwocsz3Q5Hv454kN23Pw_w7lYdfOwVnoZVdDHPcvj6tpY-o7BpOyUmDypqX9vPuUGc','2025-01-22 11:08:00'),(583,'eVTVXt_rR4LctMBQYBlI1B:APA91bESEEP9-rWsKvsJfe5bsFAz7Kjkd6I_roG-U_2Q4mYtaoOCQiEKGOPJ1yvngJzKQ2H_uibh5GZVT_06V9tXrUcJ-TyAoykC2Ft8Bim_f3qX2mdjoos','2025-01-22 11:08:01'),(584,'eVTVXt_rR4LctMBQYBlI1B:APA91bFlnhakWU3bzAk-Ntpxgu7HK35okLucQiw_dIYlD_wrq78fLQ9YfO1cPsT9nhU0CHiH-rOabeejptkJnyeTzIrgQnJX4fX7QukL1O6bG85oBZaSiY0','2025-01-22 11:08:10'),(585,'eVTVXt_rR4LctMBQYBlI1B:APA91bGXP5mqcvThkXpkB7Ng8PnIP1ou2AowQN6XMxzR6vOc82ORZXau71MpXrTfPeWUcugeV57R2FxRhvodncicTpdSffTWnwpfcP-Vwf029H__0ScVZw8','2025-01-22 11:08:10'),(586,'eVTVXt_rR4LctMBQYBlI1B:APA91bEN--4UMC0l6iKKNaapnVZ0BnScs5Rg8OHBeCrvPBH6sQsdEMNYs2hr_26_7l5-rkHVfx7x2nspy9CrjDLo6cBNr05rrgxMz9T95vgs91UFiD-3DoE','2025-01-22 11:08:11'),(596,'cwcwcDOfotK18U1Op0rRC6:APA91bGKjm2RM7B2kisdDTOQctMGUL1KLjbV3QGMp3XgJ7QN3l-9XWpuKhz6fU8v4o4tHutYROUCNOK4ISfivoJLiQiMwmuPC4OTD2iO-gOmOavpbDzPfMQ','2025-01-22 18:34:37'),(847,'fj4LGY9X0nGGsPph1PTfda:APA91bFjo2cJ3zDd55c4IPfJRsoUqSHLrGqqL2Mp33xNYZyQnpqLzWXQjtuuEtntWZm8ZLRI4fMQ5Yd_wZWgkY4qL6qqCdu7GmDaVNRWhdri2L9NxXFHaro','2025-01-23 20:06:30'),(848,'fj4LGY9X0nGGsPph1PTfda:APA91bHh-3RNgU-mL_JZzjXXDjAHsZHKNXupSsCskcRqAQ_1D2cVX3Ir4hsZ7ZKDA3n3IMPbkxbCrkNgvn2BE4NMjj_Le0eYe9CR5l_WVuvXTeMk4ixSLYE','2025-01-23 20:06:30'),(865,'eVTVXt_rR4LctMBQYBlI1B:APA91bEGsXanxM68fpB0CS1bVo3ZmOYYySOGorfQmgfkVS6waltrNepTcNT1ISldytKqcsfRO4z8FI5mD8oXVMaSpSiu9j2r7UcDaO-7ILdOrn3YF3PLF5I','2025-01-24 12:49:36'),(2294,'eVTVXt_rR4LctMBQYBlI1B:APA91bExrmYeeOVKOk7awkwwHOH2PT6f7iFbJzpehu4gX1ZteFWNK0PBoRtuqBEuEaSXrQSzkrVvl_1cJEq5VMnZSTNE2skDe8XHmtlWqFu5c5VCY_8-P2I','2025-02-05 12:07:09'),(4277,'eIW5ZKSH65vFMWWYl2bilF:APA91bHKTwA_nYM5znInm2PBWdzwCh6WItWsBohRiiVWcrJ9iw6HjAfBx4WvJEQt0hjIrfPhJVAhHWHAHSnTE_U7ZQyWtzSQWTiihrxjeMi_G7Yd5Skfm50','2025-02-25 18:16:46'),(4278,'eIW5ZKSH65vFMWWYl2bilF:APA91bHUjVk2EMJXzC_n8u2GS35V7AvfUEjhqpc5dd_HFH3JBMe46E3uoIyVK-9Us0DFXMsCUxTJWyqHLEzpdq0kfbma_ljNVmj5Mi7GgB5Dq-bQccksXT0','2025-02-25 18:16:46'),(4279,'eIW5ZKSH65vFMWWYl2bilF:APA91bG-EcU_m6YQ36UdJBGiTCcTChgmGRoo96ZoDZoR3uhHy_jLLF1mgYBvJ7KlxT9wHpM_0G_zv9EMmefXBFRF941ZwT_3fU7XK4zNKZ7N-84Vn6jFsc0','2025-02-25 18:16:47'),(4280,'eIW5ZKSH65vFMWWYl2bilF:APA91bEoUI1f7yD0mtciMx_K1Bp8-1WKhCa5FVI6F8hDocOI2SlMElQSB9GT2ImJXo4WS1VwmOEENOW-lAwODin6AxVzqTBSSNsH7Yv1oIOOoOTpioDwsNM','2025-02-25 18:16:51');

#
# Structure for table "empresas"
#

CREATE TABLE `empresas` (
  `id_empresa` int NOT NULL AUTO_INCREMENT,
  `nome_empresa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1' COMMENT '0=inativa, 1=ativa',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_empresa`)
) ENGINE=MyISAM AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "empresas"
#

INSERT INTO `empresas` VALUES (45,'Harpia',NULL,NULL,NULL,1,'2024-12-16 06:39:04','2024-12-16 06:39:51'),(46,'Kalay Brasil',NULL,NULL,NULL,1,'2024-12-16 06:39:11','2024-12-16 06:39:25'),(47,'Angular',NULL,NULL,NULL,1,'2024-12-16 06:56:48','2024-12-16 06:56:48'),(48,'Tulipa Parana Eventos',NULL,NULL,NULL,1,'2024-12-16 06:56:48','2024-12-16 06:56:48'),(50,'Tendencia',NULL,NULL,NULL,1,'2025-01-06 16:14:30','2025-01-06 16:14:30'),(51,'Loterica',NULL,NULL,NULL,1,'2025-01-06 16:15:07','2025-01-06 16:15:07'),(52,'Confeiteira',NULL,NULL,NULL,1,'2025-01-09 18:45:32','2025-01-09 18:45:32'),(53,'Diversos',NULL,NULL,NULL,1,'2025-02-13 18:08:28','2025-02-13 18:08:28');

#
# Structure for table "pagamento"
#

CREATE TABLE `pagamento` (
  `id_pagamento` int NOT NULL AUTO_INCREMENT,
  `metodo_pagamento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_pagamento`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "pagamento"
#

INSERT INTO `pagamento` VALUES (1,'Dinheiro'),(2,'Voucher'),(3,'Debito'),(4,'Crédito'),(5,'Sodexo / Pluxee Refeição'),(6,'Pix Manual'),(7,'VR Refeição / Alimentação'),(8,'Online Pix'),(9,'Não informado'),(10,'Ifood');

#
# Structure for table "pedidos"
#

CREATE TABLE `pedidos` (
  `id_pedido` int NOT NULL AUTO_INCREMENT,
  `num_pedido` int DEFAULT NULL,
  `data_pedido` date DEFAULT NULL,
  `hora_pedido` time DEFAULT NULL,
  `fk_cliente_id` int DEFAULT NULL,
  `fk_pagamento_id` int DEFAULT NULL,
  `taxa_entrega` decimal(10,2) DEFAULT NULL,
  `sub_total` decimal(10,2) DEFAULT NULL,
  `fk_entrega_id` int NOT NULL DEFAULT '0',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_pagamento` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0=pendente, 1=pago',
  `is_retirada` tinyint(1) DEFAULT '0',
  `data_insert` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora do cadastro do pedido no sistema',
  `origem` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_pedido` tinyint NOT NULL DEFAULT '0' COMMENT '0=Em análise, 1=Em produção, 2=Pronto para entrega, 3=Finalizado, 4=Cancelado',
  PRIMARY KEY (`id_pedido`),
  KEY `fk_cliente_id` (`fk_cliente_id`),
  KEY `fk_pagamento_id` (`fk_pagamento_id`),
  KEY `fk_entrega_id` (`fk_entrega_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "pedidos"
#


#
# Structure for table "produto"
#

CREATE TABLE `produto` (
  `id_produto` int NOT NULL AUTO_INCREMENT,
  `nome_produto` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preco_produto` decimal(10,2) DEFAULT NULL,
  `fk_categoria_id` int DEFAULT NULL,
  `activated` int DEFAULT '1',
  `img` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_produto`),
  KEY `fk_categoria_id` (`fk_categoria_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "produto"
#

INSERT INTO `produto` VALUES (1,'Marmita P',16.00,1,1,'produto_67d95f99c26a5.jpeg'),(2,'Marmita M',19.00,1,1,'produto_67d95fb5a3206.jpeg'),(3,'Marmita G',21.00,1,1,'produto_67d95fbf91000.jpeg'),(4,'Marmita P Salada + Carne',16.00,1,1,'produto_67d95fcaa51aa.jpeg'),(5,'Marmita M Salada + Carne',19.00,1,1,'produto_67d95ff34db13.jpeg'),(6,'Feijoada Completa P',23.00,2,1,'produto_67d95fe542026.jpeg'),(7,'Feijoada Completa M',33.00,2,1,'produto_67d9603948621.jpeg'),(8,'Feijoada Completa G',39.00,2,1,'produto_67d95fff7bbe5.jpeg'),(9,'Somente a Feijoada P',19.00,2,1,'produto_67d9600be5c4d.jpeg'),(10,'Somente a Feijoada M',23.00,2,1,'produto_67d96016d60e6.jpeg'),(11,'Somente a Feijoada G',29.00,2,1,'produto_67d960213927d.jpeg'),(12,'Strogonoff de Frango(acompanha batata palha)',0.00,4,0,NULL),(14,'Adicional Salada',4.00,5,1,NULL),(15,'Bananinha a Milanesa',0.00,5,1,NULL),(16,'Coca-Cola Lata',6.00,3,1,'produto_67d331b6c6d1d.jpeg'),(19,'Coca-Cola 200m',4.00,3,1,NULL),(20,'Coca-cola 600ml',8.00,3,1,'produto_67d331c2394a6.jpeg'),(26,'Carne Moida com Batatas',0.00,4,1,NULL),(27,'Massas',0.00,12,0,NULL),(28,'Quirera com Suan',0.00,13,1,NULL),(29,'Coca-Cola Lata Zero 350ml',6.00,3,1,'produto_67d331d0e3712.jpeg'),(30,'Lasanha + Coca 200ml',23.00,14,0,NULL),(31,'Escondidinho de carne + Coca 200ml',23.00,14,0,NULL),(32,'File de Frango a Parmegiana',0.00,4,1,NULL),(33,'Lasanha a Bolonhesa',0.00,4,1,NULL),(34,'File de Frango Empanado',0.00,4,1,NULL),(35,'Carne de Panela com Aipim',0.00,4,1,NULL),(36,'Linguiça Assada',0.00,4,1,NULL),(38,'File de Peixe Empanado',0.00,4,1,NULL),(43,'Fanta Guarana 1.5l',9.00,3,1,NULL),(45,'Coca Cola 2 Litros',12.00,3,1,NULL),(46,'Almondegas ao Molho Vermelho',0.00,4,1,NULL),(47,'Guaraná Fanta 2 Litros',10.00,3,1,NULL),(48,'Arroz Pp',4.00,5,0,NULL),(50,'Couve Pp',6.00,5,0,NULL),(51,'Feijão Preto',0.00,5,0,NULL),(52,'Pure Pp',6.00,5,0,NULL),(53,'Coxinha da Asa Empanada',0.00,4,0,NULL),(54,'Coxa e Sobre Coxa Assada (sem osso)',0.00,4,0,NULL),(55,'Bife Acebolado(posta branca)',0.00,4,1,NULL),(56,'Vinagrete Potinho',2.00,5,0,NULL),(57,'Potinho de Vinagrete',2.00,5,0,NULL),(58,'Linguiça Assada 2',0.00,4,0,NULL),(60,'Feijão Branco',0.00,5,1,NULL),(61,'Salada P',10.00,5,0,NULL),(62,'Frango Americano(coxinha da asa empanada)',0.00,4,1,NULL),(63,'Frango a Passarinho',0.00,4,0,NULL),(64,'SOMENTE ARROZ G',0.00,5,1,NULL),(65,'Potinho de Farofa',2.00,5,1,NULL),(66,'File de Frango Grelhado(Acebolado)',0.00,4,1,NULL),(67,'AD CARNE 5.00',5.00,4,1,NULL),(68,'Caldo de Aipim P',16.00,13,1,NULL),(69,'Caldo de Aipim Pp',11.00,13,1,NULL),(70,'Dobradinha com Calabresa',0.00,13,1,NULL),(71,'Caldo de Aipim com Calabresa',0.00,13,1,NULL),(72,'Frango Assado(coxa e sobrecoxa sem osso)',0.00,4,1,NULL),(73,'Carne Moida com Legumes',0.00,4,1,NULL);

#
# Structure for table "pedido_itens"
#

CREATE TABLE `pedido_itens` (
  `id_pedido_item` int NOT NULL AUTO_INCREMENT,
  `fk_pedido_id` int DEFAULT NULL,
  `fk_produto_id` int DEFAULT NULL,
  `quantidade` int DEFAULT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `data_insert` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pedido_item`),
  KEY `fk_pedido_id` (`fk_pedido_id`),
  KEY `fk_produto_id` (`fk_produto_id`),
  CONSTRAINT `pedido_itens_ibfk_1` FOREIGN KEY (`fk_pedido_id`) REFERENCES `pedidos` (`id_pedido`),
  CONSTRAINT `pedido_itens_ibfk_2` FOREIGN KEY (`fk_produto_id`) REFERENCES `produto` (`id_produto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "pedido_itens"
#


#
# Structure for table "ficha_tecnica"
#

CREATE TABLE `ficha_tecnica` (
  `id_ficha` int NOT NULL AUTO_INCREMENT,
  `fk_produto_id` int NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `modo_preparo` text COLLATE utf8mb4_unicode_ci,
  `rendimento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempo_preparo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calorias` decimal(10,2) DEFAULT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ficha`),
  KEY `fk_produto_id` (`fk_produto_id`),
  CONSTRAINT `fk_ficha_produto` FOREIGN KEY (`fk_produto_id`) REFERENCES `produto` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "ficha_tecnica"
#


#
# Structure for table "produto_acomp"
#

CREATE TABLE `produto_acomp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fk_produto_id` int DEFAULT NULL,
  `fk_acomp_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_produto_id` (`fk_produto_id`),
  KEY `fk_acomp_id` (`fk_acomp_id`)
) ENGINE=MyISAM AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "produto_acomp"
#

INSERT INTO `produto_acomp` VALUES (1,1,1),(2,1,2),(3,1,3),(4,1,4),(5,1,5),(6,2,1),(7,2,2),(8,2,3),(9,2,4),(10,2,5),(11,3,1),(12,3,2),(13,3,3),(14,3,4),(15,3,5),(16,4,3),(17,5,3),(18,12,7),(21,26,7),(22,27,8),(23,28,9),(24,32,7),(25,33,7),(26,34,7),(30,9,10),(31,10,10),(32,11,10),(33,8,10),(34,7,10),(35,6,10),(39,35,7),(40,15,10),(41,53,7),(42,55,7),(43,54,7),(44,58,7),(46,38,7),(47,36,7),(48,46,7),(49,60,12),(50,51,12),(51,62,7),(52,63,7),(53,66,7),(54,70,9),(55,71,9),(56,73,7);

#
# Structure for table "produto_acomp_regras"
#

CREATE TABLE `produto_acomp_regras` (
  `id_regra` int NOT NULL AUTO_INCREMENT,
  `fk_acomp_id` int NOT NULL,
  `is_required` tinyint(1) DEFAULT '0',
  `min_escolhas` int DEFAULT '0',
  `max_escolhas` int DEFAULT '1',
  `permite_repetir` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_regra`),
  KEY `fk_acomp_id` (`fk_acomp_id`),
  CONSTRAINT `fk_regras_acomp` FOREIGN KEY (`fk_acomp_id`) REFERENCES `acomp` (`id_acomp`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "produto_acomp_regras"
#

INSERT INTO `produto_acomp_regras` VALUES (23,10,0,1,1,0),(25,4,0,1,10,0),(26,5,0,1,10,0),(27,3,1,1,1,0),(29,8,1,1,2,0),(30,2,1,1,3,0),(31,9,1,1,1,0),(32,7,1,1,1,0),(33,7,1,1,1,0),(34,7,1,1,1,0),(35,12,0,0,1,0),(36,1,1,1,1,0);

#
# Structure for table "produto_regras"
#

CREATE TABLE `produto_regras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fk_produto_id` int NOT NULL,
  `fk_regra_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_produto_id` (`fk_produto_id`),
  KEY `fk_regra_id` (`fk_regra_id`)
) ENGINE=MyISAM AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "produto_regras"
#

INSERT INTO `produto_regras` VALUES (25,1,28),(26,2,28),(27,3,28),(33,1,30),(34,2,30),(35,3,30),(36,1,27),(37,2,27),(38,3,27),(39,4,27),(40,5,27),(41,12,24),(42,13,24),(43,25,24),(44,26,24),(45,32,24),(46,33,24),(47,34,24),(48,1,25),(49,2,25),(50,3,25),(51,4,25),(52,5,25),(53,1,26),(54,2,26),(55,3,26),(56,4,26),(57,5,26),(58,6,23),(59,7,23),(60,8,23),(61,9,23),(62,10,23),(63,11,23);

#
# Structure for table "sub_acomp"
#

CREATE TABLE `sub_acomp` (
  `id_subacomp` int NOT NULL AUTO_INCREMENT,
  `nome_subacomp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preco_subacomp` decimal(10,2) DEFAULT '0.00',
  `fk_acomp_id` int DEFAULT NULL,
  `activated` int DEFAULT '1',
  PRIMARY KEY (`id_subacomp`),
  KEY `fk_acompanhamento_id` (`fk_acomp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "sub_acomp"
#

INSERT INTO `sub_acomp` VALUES (1,'Feijão com Feijoada(contem pedaços da feijoada)',5.00,1,1),(2,'Branco',0.00,1,1),(3,'Preto',0.00,1,1),(5,'Arroz',0.00,2,1),(6,'Macarrao ao Molho Vermelho',0.00,2,0),(7,'Farofa de Bacon',0.00,2,0),(8,'File de Frango Empanado',0.00,3,0),(10,'Ovos Fritos',0.00,3,1),(11,'Sem Carne',0.00,3,1),(12,'Ovo',2.00,4,1),(13,'Salada',4.00,5,1),(20,'pp',13.00,7,1),(21,'p',22.00,7,1),(22,'m',25.00,7,1),(23,'g',31.00,7,1),(29,'Sem feijão',0.00,1,1),(32,'Refogado de Repolho com Cenoura',0.00,2,0),(33,'Farofa de Bacon',0.00,2,0),(34,'File de Frango Grelhado(acebolado)',0.00,3,0),(35,'Carne Moida com Batatas',0.00,3,0),(36,'Escondidinho de Carne Moída',20.00,8,1),(37,'Arroz',4.00,8,1),(38,'Pure de Batatas',0.00,2,0),(39,'Macarrao Branco(sem molho)',0.00,2,1),(40,'File de Frango a Parmegiana',0.00,3,1),(41,'Almondegas ao Molho Vermelho',0.00,3,1),(42,'pp',11.00,9,1),(43,'p',16.00,9,1),(44,'m',19.00,9,1),(45,'g',21.00,9,1),(46,'Regogado de Abobrinha com Cenoura',0.00,2,0),(47,'Macarrao com Salsicha(vina sem molho)',0.00,2,0),(48,'File de Peixe Empanado',0.00,3,0),(49,'Farofa de Ovos',0.00,2,0),(50,'Refogado de Chuchu',0.00,2,1),(51,'Farofa de Calabresa',0.00,2,0),(52,'Macarrao Alho e Oleo',0.00,2,0),(53,'Strogonoff de Frango(acompanha batata palha)',0.00,3,0),(54,'Couve com Bacon',0.00,2,0),(55,'Porco grelhado(acebolado sem osso)',0.00,3,0),(56,'Macarrao com Salsicha(vina com molho)',0.00,2,0),(57,'Lasanha a Bolonhesa',0.00,3,0),(59,'Refogado de Abobrinha com Cenoura',0.00,2,0),(60,'Lombo Suino Grelhado(porco acebolado)',0.00,3,0),(61,'Banana',6.00,10,1),(62,'Carne de Panela com Aipim',0.00,3,0),(63,'Polenta Cremosa',0.00,2,0),(64,'Linguiça Assada',0.00,3,0),(66,'Lasanha Bolonhesa',20.00,8,1),(67,'Picadinho de Carne com legumes',0.00,3,0),(68,'Batata doce',0.00,2,0),(69,'Refogado de Repolho',0.00,2,0),(70,'Farofa de Pernil',0.00,2,0),(72,'Macarrao com Calabresa',0.00,2,0),(73,'Carne em Tiras Grelhada Acebolada(posta vermelha)',0.00,3,0),(74,'Batata Frita',0.00,2,0),(75,'Macarrao a Bolonhesa',0.00,2,0),(76,'Bisteca Grelhada Acebolada',0.00,3,0),(77,'Talher',0.00,11,1),(78,'Tutu de feijao(contem bacon)',0.00,2,0),(79,'Farofa de Linguiça',0.00,2,0),(80,'Batata Doce Assada',0.00,2,0),(81,'Macarrao com Presunto(tipo parafuso)',0.00,2,0),(82,'Carne em Tiras Empanada(posta vermelha)',0.00,3,0),(83,'Bisteca Suina Empada',0.00,3,0),(84,'Frango Americano(coxinha da asa empanada)',0.00,3,0),(85,'File de Peixe Empanado Merluza',0.00,3,0),(86,'File de Peixe Empanado(sem espinhos)',0.00,3,0),(87,'Couve Temperada(sem ser refogada)',0.00,2,0),(88,'Frango Empanado',0.00,3,0),(89,'Macarrao com Vina(salsicha com molho vermelho)',0.00,2,0),(90,'Bife Acebolado(posta branco)',0.00,3,0),(91,'Refogado de Repolho com Couve',0.00,2,0),(92,'Aipim Frito',0.00,2,0),(93,'Mix de Vegetais Assados',0.00,2,0),(94,'Kibe Frito',0.00,2,0),(95,'Picadao Bovino com Aipim(sem osso)',0.00,3,0),(96,'Macarrão com Bacon',0.00,2,0),(97,'Bife a Parmegiana',0.00,3,0),(98,'Carne em Tira a Milanesa(posta branca)',0.00,3,0),(99,'Farofa de Toucinho',0.00,2,0),(100,'Bolinho de Arroz',0.00,2,0),(102,'Farofa de Frango(com farinha de milho)',0.00,2,0),(103,'Macarrao ao Molho Shoyo',0.00,2,0),(104,'Carne de Panela com Batatas',0.00,3,0),(105,'Carne Moida com Aipim',0.00,3,0),(106,'Refogado de Vagem com Cenoura',0.00,2,0),(107,'Batata Doce Frita',0.00,2,0),(108,'Carne Bovina em Tiras Acebolada(fraldinha)',0.00,3,0),(109,'Macarrão ao Molho Branco',0.00,2,0),(110,NULL,NULL,NULL,1),(111,NULL,NULL,NULL,1),(112,NULL,NULL,NULL,1),(113,NULL,NULL,NULL,1),(114,'Pp (6 UNIDADES)',6.00,10,1),(115,'Refogado de Abobrinha',0.00,2,0),(117,'P (10 UNIDADES)',12.00,10,1),(118,'Coxinha da Asa Empanada',0.00,2,0),(119,'Coxa e sobre Coxa Asada (sem osso)',0.00,3,0),(120,'PP',8.00,12,1),(121,'P',12.00,12,1),(122,'M',18.00,12,1),(123,'Frango a Passarinho',0.00,3,0),(125,'Legumes Assados',0.00,2,0),(126,'BATATA ASSADA',0.00,2,0),(127,'Carne Moida com Legumes',0.00,3,0),(128,'Frango Assado(coxa e sobrecoxa sem osso)',0.00,3,0);

#
# Structure for table "ficha_tecnica_ingredientes"
#

CREATE TABLE `ficha_tecnica_ingredientes` (
  `id_ingrediente` int NOT NULL AUTO_INCREMENT,
  `fk_ficha_id` int NOT NULL,
  `nome_ingrediente` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` decimal(10,3) DEFAULT NULL,
  `unidade_medida` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custo_unitario` decimal(10,2) DEFAULT NULL,
  `custo_total` decimal(10,2) DEFAULT NULL,
  `fk_subacomp_id` int DEFAULT NULL,
  PRIMARY KEY (`id_ingrediente`),
  KEY `fk_ficha_id` (`fk_ficha_id`),
  KEY `fk_subacomp_id` (`fk_subacomp_id`),
  CONSTRAINT `fk_ingrediente_ficha` FOREIGN KEY (`fk_ficha_id`) REFERENCES `ficha_tecnica` (`id_ficha`) ON DELETE CASCADE,
  CONSTRAINT `fk_ingrediente_subacomp` FOREIGN KEY (`fk_subacomp_id`) REFERENCES `sub_acomp` (`id_subacomp`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "ficha_tecnica_ingredientes"
#


#
# Structure for table "pedido_item_acomp"
#

CREATE TABLE `pedido_item_acomp` (
  `id_pedido_item_acomp` int NOT NULL AUTO_INCREMENT,
  `fk_pedido_item_id` int DEFAULT NULL,
  `fk_acomp_id` int DEFAULT NULL,
  `fk_subacomp_id` int DEFAULT NULL,
  `quantidade` int DEFAULT NULL,
  `preco_unitario` decimal(10,2) DEFAULT NULL,
  `data_insert` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pedido_item_acomp`),
  KEY `fk_pedido_item_id` (`fk_pedido_item_id`),
  KEY `fk_acomp_id` (`fk_acomp_id`),
  KEY `fk_subacomp_id` (`fk_subacomp_id`),
  CONSTRAINT `pedido_item_acomp_ibfk_1` FOREIGN KEY (`fk_pedido_item_id`) REFERENCES `pedido_itens` (`id_pedido_item`),
  CONSTRAINT `pedido_item_acomp_ibfk_2` FOREIGN KEY (`fk_acomp_id`) REFERENCES `acomp` (`id_acomp`),
  CONSTRAINT `pedido_item_acomp_ibfk_3` FOREIGN KEY (`fk_subacomp_id`) REFERENCES `sub_acomp` (`id_subacomp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "pedido_item_acomp"
#


#
# Structure for table "subacomp_ingredientes"
#

CREATE TABLE `subacomp_ingredientes` (
  `id_ingrediente` int NOT NULL AUTO_INCREMENT,
  `fk_subacomp_id` int NOT NULL,
  `nome_ingrediente` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` decimal(10,2) NOT NULL,
  `unidade_medida` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `custo_unitario` decimal(10,2) NOT NULL,
  `custo_total` decimal(10,2) NOT NULL,
  `data_criacao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ingrediente`),
  KEY `fk_subacomp_id` (`fk_subacomp_id`),
  CONSTRAINT `fk_subacomp_ingredientes_subacomp` FOREIGN KEY (`fk_subacomp_id`) REFERENCES `sub_acomp` (`id_subacomp`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "subacomp_ingredientes"
#


#
# Structure for table "user_tokens"
#

CREATE TABLE `user_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "user_tokens"
#

INSERT INTO `user_tokens` VALUES (1,1,'7071758c6854aed0fd17e6ce368631e7ed51914b151b957e59ac8bb07000d16e','2025-01-11 23:45:12','2024-12-12 17:45:12'),(2,1,'200aa6b6c7cd6048477296dc778f1d447361da1aaac1944ad618a9bea1474ee7','2025-01-11 23:48:43','2024-12-12 17:48:43'),(3,1,'3582c3063f9dff8caa02e207b3e8b7d93e604b48037ac7dc001e66acc560c09c','2025-01-11 20:50:57','2024-12-12 17:50:40'),(4,1,'c69151384668218a6ab5a30daa19ddb1dccb47c9bc4ff12aa02549dcc7a5167f','2025-02-07 10:37:10','2024-12-12 17:52:13'),(5,1,'34b52cde65b54c4df24db62e52f67e4bfd9945c541ebe8479f38d369cf5bdedb','2025-02-27 19:49:36','2025-01-04 16:29:21'),(6,1,'80fe987f0abfd03b49089d9bfb6ce4457cd575ebf01bbdd233b8bde495eabaf7','2025-03-02 17:53:17','2025-01-04 17:02:07'),(7,1,'fb03efca5526c0f14ee57950d7a3d52fbc77e0b1b619ec1184cbb3b0f645df9e','2025-02-08 02:30:51','2025-01-06 02:37:12'),(8,1,'966fb74eb6fe59555b049a7236f19c55c3139b051a17fc973422887a097aef9a','2025-02-05 19:32:48','2025-01-06 14:45:39'),(9,1,'e92f6380b8a3cb2c431bc19ac0abdba2b2f57a5051d73c28b159916feafe4f71','2025-02-12 18:18:07','2025-01-10 16:22:36'),(10,1,'0d9104693648faa9ba5cc546434756dc88d1de24db5e01f5dfde4440cac8f26a','2025-02-11 09:54:40','2025-01-12 12:54:40'),(11,1,'0e71b9a86ba67fc5ccbc328ce78fbac24d5e1368410fb3d371508609a0d4fc0d','2025-02-13 16:09:08','2025-01-14 19:09:08'),(12,1,'a4f65166140874de781e92de4e1517835585550b7adf7a831c570c6494835672','2025-02-13 16:15:41','2025-01-14 19:15:41'),(13,1,'9e005bb10d416ada974e158d1dbeed7fa8ef3d8963be4f4ecaeb7e29d2d4ec10','2025-02-27 18:58:21','2025-01-15 16:32:01'),(14,1,'04c7d37e2d5f5eae1662a2adff8e3b7b974883786b62c1938da820a5b1b50418','2025-03-03 18:24:59','2025-01-22 18:34:31'),(15,1,'875cdd932b0531e0a6e07c3e8762623a999c2262dba14e51dd92594172e72ed3','2025-03-02 11:05:12','2025-01-31 14:05:12'),(16,1,'ce8e796136057155be50e471fb1af9383609c09eb62676807b6e358d59650535','2025-03-08 16:12:13','2025-01-31 19:19:44'),(17,1,'6adb0a52812078de2fd621d121d03831663da65c9aa510a09c4728fbf223bd28','2025-03-07 09:06:54','2025-02-05 12:06:54'),(18,1,'549ab3ba1394cab2cff0c9471038e9e9d0ddae179b5405d41731ae6f121b0985','2025-03-29 22:31:04','2025-02-06 16:56:18'),(19,1,'f50bec073d4731bc37eb305c94f730742da57e3b7375ce805ea4519dfcc493bf','2025-03-13 12:11:20','2025-02-10 10:54:36'),(20,1,'244a30476feb47f38bbedd71f526ce59e39654cca066f362793f4b6b4c5a0849','2025-03-27 15:16:40','2025-02-25 18:16:40'),(21,1,'5d8f59f8d796d8172984334946bccf52d763f297ee2b21cac4e637259303735e','2025-04-05 20:34:13','2025-03-01 00:11:02'),(22,1,'f633e3c0b634d9901bd612d8fce5d4a188bbe8077e757233b45e005bc0b7a72e','2025-04-05 17:48:25','2025-03-06 17:48:25'),(23,1,'aa5a56558ca2cb2724b4069ed01997d58cdfb1fff74193cb79e7fee2f40ca287','2025-04-05 18:47:50','2025-03-06 18:47:50'),(24,1,'3f9eada8b91dcff0e7b8880defd9e481869bd7047b3ed57a876de7068d29cde1','2025-04-08 18:37:39','2025-03-09 21:37:39'),(25,1,'52d76d88b976bf202155a5ad77177060aa80fe42a48bf0592f7de8e579cb9ef8','2025-04-08 18:38:25','2025-03-09 21:38:25'),(26,1,'7f94c8a299043c18555df44ade59577b706275f84d66e0ed22adf8435d353dba','2025-04-17 11:18:20','2025-03-10 23:09:48');

#
# Structure for table "users"
#

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "users"
#

INSERT INTO `users` VALUES (1,'lunchefit@gmail.com','ea0abd7ea453aff654eb7a5b74a3dd8a');

#
# Structure for table "usuario_preferencias"
#

CREATE TABLE `usuario_preferencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_atualizacao` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_tipo_unique` (`user_id`,`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "usuario_preferencias"
#

INSERT INTO `usuario_preferencias` VALUES (1,1,'notificacao_som','1','2025-03-13 13:28:01');

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

#
# Data for table "boletos"
#

#
# Structure for table "notas_fiscais"
#

CREATE TABLE `notas_fiscais` (
  `id_nfe` int NOT NULL AUTO_INCREMENT,
  `numero_nfe` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chave_nfe` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_emissao` date NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `fornecedor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj_fornecedor` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xml_nfe` text COLLATE utf8mb4_unicode_ci,
  `pdf_nfe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_nfe`),
  UNIQUE KEY `numero_nfe` (`numero_nfe`),
  UNIQUE KEY `chave_nfe` (`chave_nfe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Structure for table "boletos_nfe"
#

CREATE TABLE `boletos_nfe` (
  `id_boleto_nfe` int NOT NULL AUTO_INCREMENT,
  `fk_boleto_id` int NOT NULL,
  `fk_nfe_id` int NOT NULL,
  `parcela` int DEFAULT NULL COMMENT 'Número da parcela (1/3, 2/3, 3/3)',
  `total_parcelas` int DEFAULT NULL COMMENT 'Total de parcelas da NF',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_boleto_nfe`),
  KEY `fk_boleto_id` (`fk_boleto_id`),
  KEY `fk_nfe_id` (`fk_nfe_id`),
  CONSTRAINT `fk_boleto_nfe_boleto` FOREIGN KEY (`fk_boleto_id`) REFERENCES `boletos` (`id_boleto`) ON DELETE CASCADE,
  CONSTRAINT `fk_boleto_nfe_nfe` FOREIGN KEY (`fk_nfe_id`) REFERENCES `notas_fiscais` (`id_nfe`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

#
# Data for table "boletos_nfe"
#

#
# Alterações na tabela boletos
#

ALTER TABLE `boletos` 
ADD COLUMN `numero_nfe` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `desconto`;

ALTER TABLE `boletos` 
ADD COLUMN `chave_nfe` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `numero_nfe`;

ALTER TABLE `boletos` 
ADD COLUMN `parcela` int DEFAULT NULL COMMENT 'Número da parcela (1/3, 2/3, 3/3)' AFTER `chave_nfe`;

ALTER TABLE `boletos` 
ADD COLUMN `total_parcelas` int DEFAULT NULL COMMENT 'Total de parcelas da NF' AFTER `parcela`;

#
# Structure for table "anotai_pedidos"
#

CREATE TABLE `anotai_pedidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `anotai_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `anotai_id` (`anotai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
