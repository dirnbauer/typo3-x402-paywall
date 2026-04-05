CREATE TABLE pages (
    tx_x402_paywall_enabled tinyint(1) unsigned DEFAULT 0 NOT NULL,
    tx_x402_paywall_price varchar(20) DEFAULT '' NOT NULL,
    tx_x402_paywall_description varchar(255) DEFAULT '' NOT NULL,
);

#
# Payment transaction log for revenue analytics (v1.1)
#
CREATE TABLE tx_x402_payment_log (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,

    page_uid int(11) unsigned DEFAULT '0' NOT NULL,
    content_type varchar(100) DEFAULT 'page' NOT NULL,
    content_uid int(11) unsigned DEFAULT '0' NOT NULL,
    request_uri text,
    amount varchar(30) DEFAULT '' NOT NULL,
    currency varchar(10) DEFAULT 'USDC' NOT NULL,
    network varchar(50) DEFAULT '' NOT NULL,
    tx_hash varchar(255) DEFAULT '' NOT NULL,
    payer_address varchar(255) DEFAULT '' NOT NULL,
    facilitator_response text,
    status varchar(20) DEFAULT 'settled' NOT NULL,
    user_agent text,
    ip_hash varchar(64) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY page_uid (page_uid),
    KEY content_type (content_type),
    KEY crdate (crdate),
    KEY status (status),
    KEY network (network)
);
