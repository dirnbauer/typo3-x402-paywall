CREATE TABLE pages (
    tx_x402_paywall_enabled tinyint(1) unsigned DEFAULT 0 NOT NULL,
    tx_x402_paywall_price varchar(20) DEFAULT '' NOT NULL,
    tx_x402_paywall_description varchar(255) DEFAULT '' NOT NULL,
);
