#RpMagento2
Order import Extension for Magento 2

This Extension is help full for Importing order from CSV



Installation Guide



Connect to your website source folder with FTP/SFTP/SSH client
and upload all the files and folders from the extension package
to the corresponding app/code/ folder of your Magento installation:



Connect to your Magento directory with SSH.


Run 3 following commands:

php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
