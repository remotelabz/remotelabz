# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.define "web" do |web|
    web.vm.box = "ubuntu/bionic64"

    web.vm.hostname = "remotelabz"

    web.vm.network "forwarded_port", guest: 8000, host: 8000
    web.vm.network "forwarded_port", guest: 8888, host: 8888
  
    web.vm.synced_folder ".", "/var/www/html/remotelabz", owner: "www-data", group: "www-data"
  
    web.vm.provision "shell", path: "vagrant/provision.sh"
  end
    
  # config.vm.define "mysql" do |mysql|
  #   mysql.vm.box = "mysql"
  #   mysql.vm.hostname = "mysql"

  #   mysql.vm.network "private_network", type: "dhcp", virtualbox__intnet: "remotelabz"
  # end
end
  