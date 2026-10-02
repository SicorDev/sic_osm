CREATE TABLE tx_sicosm_domain_model_map (
    latitude decimal(10,7) NOT NULL DEFAULT '0.0000000',
    longitude decimal(10,7) NOT NULL DEFAULT '0.0000000',
    zoom int(11) NOT NULL DEFAULT '15',
    marker_color varchar(7) NOT NULL DEFAULT '#d7263d',
    grayscale smallint(5) unsigned NOT NULL DEFAULT '0'
);
