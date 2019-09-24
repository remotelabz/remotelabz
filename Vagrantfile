# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "generic/ubuntu1804"
  
  config.vm.provider :libvirt do |domain|
    domain.driver = "kvm"
    domain.memory = 2048
    domain.cpus = 1
    domain.graphics_ip = '0.0.0.0'
    domain.keymap = "fr"
  end

  config.vm.network "private_network", type: "dhcp"
  config.vm.hostname = "remotelabz"
  config.vm.network "forwarded_port", guest: 80, host: 8000
  config.vm.network "forwarded_port", guest: 8888, host: 8888

  config.vm.synced_folder ".", "/opt/remotelabz", owner: "vagrant", group: "www-data"

  config.vm.provision "shell", path: "vagrant/provision.sh"
end
