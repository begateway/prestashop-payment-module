<?php return array(
    'root' => array(
        'name' => 'prestashop/begateway',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => '7664a09d9e4cab59decd3d0a878ac30c87d6446d',
        'type' => 'prestashop-begateway-module',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'begateway/begateway-api-php' => array(
            'pretty_version' => '5.1.1',
            'version' => '5.1.1.0',
            'reference' => '0238d5d45ee78cd02470fc22b75def380da345e3',
            'type' => 'library',
            'install_path' => __DIR__ . '/../begateway/begateway-api-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'prestashop/begateway' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => '7664a09d9e4cab59decd3d0a878ac30c87d6446d',
            'type' => 'prestashop-begateway-module',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
