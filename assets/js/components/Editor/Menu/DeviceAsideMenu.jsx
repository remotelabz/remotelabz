import React, { useState, useEffect } from 'react';
import Noty from 'noty';
import Remotelabz from '../../API';
import SVG from '../../Display/SVG';
import AsideMenu from './AsideMenu';
import { Button } from 'react-bootstrap';
import DeviceForm from './../Form/DeviceForm';
import NetworkInterfaceItem from './NetworkInterfaceItem';

export default function DeviceAsideMenu(props) {
    const [device, setDevice] = useState({
        id: 0,
        name: '',
        brand: '',
        model: '',
        operatingSystem: {},
        hypervisor: {},
        flavor: {},
        nbCpu: '',
        nbCore: '',
        nbSocket: '',
        nbThread: '',
        controlProtocol: {},
    });
    const [networkInterfaces, setNetworkInterfaces] = useState([]);

    useEffect(() => {
        async function getDevice() {
            const data = (await Remotelabz.devices.get(props.device)).data;
            setDevice(data);
            setNetworkInterfaces(data.networkInterfaces);
        }      
        getDevice();
    }, [props.device]);

    const onSubmitDeviceForm = async device => {
        //console.log("submit device form device", device);
        const response = await Remotelabz.devices.update(device.id, device);
        setDevice(response.data);
        new Noty({ type: 'success', text: 'Device has been updated.' }).show();
        props.onSubmitDeviceForm(device);
    }

    /**
     * @param {number} id ID of the device
     */
    const onNetworkInterfaceCreate = async (device,id) => {
        const nbnic = await Remotelabz.devices.getNbNetworkInterface(id);
        const name=device.name+"_net"+(nbnic.data+1);
        const nic = await Remotelabz.networkInterfaces.create({ device: id, name: name });
        setNetworkInterfaces([...networkInterfaces, nic.data]);
        new Noty({type: 'success', text: 'New NIC has been added to device.'}).show();
    }

    /** @param {number} id ID of the NIC */
    const onNetworkInterfaceDelete = async id => {
        await Remotelabz.networkInterfaces.delete(id);
        setNetworkInterfaces(networkInterfaces.filter(nic => nic.id != id));
        new Noty({type: 'success', text: 'NIC has been removed from device.'}).show();
    }

    
    return (<AsideMenu onClose={props.onClose}>
        <h2>Edit device</h2>
        <DeviceForm onSubmit={onSubmitDeviceForm} device={device} />
        <hr />
        <h2 className="mb-3">Network interfaces</h2>
        {networkInterfaces.map((networkInterface, index) =>
            <NetworkInterfaceItem key={networkInterface.uuid} index={index} networkInterface={networkInterface} onNetworkInterfaceDelete={onNetworkInterfaceDelete} />
        )}        
        <Button variant="success" onClick={() => onNetworkInterfaceCreate(device,device.id)} block>
            <SVG name="plus-square" className="image-sm v-sub" /> Add network interface
        </Button>
    </AsideMenu>);
}
