# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "generic/ubuntu1804"

  config.vm.hostname = "remotelabz"

  config.vm.network "forwarded_port", guest: 8000, host: 8000
  config.vm.network "forwarded_port", guest: 8888, host: 8888

  config.vm.synced_folder ".", "/var/www/html/remotelabz", owner: "www-data", group: "www-data"

  config.vm.provision "shell", path: "vagrant/provision.sh"
end
  