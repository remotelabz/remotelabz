RemoteLabz v2
=============

This project is the 2nd version of [RemoteLabz](remotelabz.univ-reims.fr), and is written with Symfony 4.

If you want to deploy to VM with Oracle VirtualBox 6, it supports nested virtualization on hosts systems that run **only AMD CPUs** ! If you deploy **AIO** on nativ linux with VT-x, we have to modify the Vagrantfile to use the kvm provider and not the qemu.

# Install

See [doc/INSTALL.md](doc/INSTALL.md).

# Install (Vagrant on Windows 10 with Oracle VirtualBox or on MAC OS)

This is the recommended **AIO** method. It was tested on **Windows 10** and **macOS mojave**.

## Requirements

- Vagrant (>=2)

## Recommendation

- At least 2GB of memory

## Steps

```bash
git clone https://gitlab.remotelabz.com/crestic/remotelabz.git
cd remotelabz
```
In the `Vagrantfile`, modify the IP in the following line in ordre you can access to your VirtualBox VM from your host. This IP must be in the same network than your host-only network interface.

`config.vm.network "private_network", ip: "192.168.50.4",virtualbox__intnet: true`

```
sudo vagrant up
```

You can now access the website via http://localhost:8000/login.  
Username : root@localhost  
Password : admin  

You can also access to the created VM via ssh  
Username : vagrant  
Password : vagrant  

## Troubleshooting

### On Windows 10, Yarn fails to install packages while starting VM

You need to enable symlink creation on Windows 10, otherwise `yarn` won't be able to install assets to VMs `/bin`.

This can be done by executing the following steps :

Go to Run dialog (`Windows + R`) and type :
```
secpol.msc
```

- Navigate to: `Local Policies > User Rights Assignment`
- Double click: `Create Symbolic Links`
- Add your `username` to the list, click `OK`
- Log off and log in again

When you log back in, you should be able to launch provision the VM without problems. If the VMs not provisioning again, destroy it before recreating it with `vagrant destroy -f`.
