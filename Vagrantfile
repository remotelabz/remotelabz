# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "generic/ubuntu1804"
  
  config.vm.provider :libvirt do |domain|
    domain.driver = "qemu"
    domain.memory = 2048
    domain.cpus = 1
    domain.graphics_port = 0
    domain.graphics_ip = '0.0.0.0'
    domain.keymap = "fr"
  end

  config.vm.network "private_network", ip: "192.168.50.4", virtualbox__intnet: true
  config.vm.hostname = "remotelabz"
  config.vm.network "forwarded_port", guest: 8000, host: 8000
  config.vm.network "forwarded_port", guest: 8888, host: 8888

  config.vm.provision "file", source: ".", destination: "/home/vagrant/remotelabz"

  config.vm.provision "shell", path: "vagrant/provision.sh"
end