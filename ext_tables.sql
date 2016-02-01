#
# Table structure for table 'tx_webkitpdf_cache'
#

CREATE TABLE tx_webkitpdf_cache (
	uid int(11) NOT NULL auto_increment,
	crdate int(11) DEFAULT '0' NOT NULL,
	urls text NOT NULL,
	filename tinytext NOT NULL,
	PRIMARY KEY (uid),
) ;
