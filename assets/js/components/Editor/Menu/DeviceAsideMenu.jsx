import React from 'react';
import Select from 'react-select';
import SVG from '../../Display/SVG';
import AsideMenu from './AsideMenu';
import { Button } from 'react-bootstrap';
import DeviceForm from './../Form/DeviceForm';

export default function DeviceAsideMenu(props) {
    const device = props.device;

    return (<AsideMenu onClose={props.onClose}>
        <h2>Edit device</h2>
        <DeviceForm onSubmit={props.onSubmitEditDevice} device={device} />
        <hr />
        <h2 className="mb-3">Network interfaces</h2>
        {device.networkInterfaces.map((networkInterface, index) => {
            const accessTypeOptions = [
                { value: '', label: 'None', id: networkInterface.id },
                { value: 'VNC', label: 'VNC', id: networkInterface.id }
            ];

            return <div key={networkInterface.uuid} className="device-network-interface-item px-3 py-3 mb-3">
                <h4 className="mb-2">NIC #{index + 1}</h4>
                <div className="form-group">
                    <label className="form-label">Access type</label>
                    <Select
                        options={accessTypeOptions}
                        menuPlacement={'top'}
                        className='react-select-container'
                        classNamePrefix="react-select"
                        defaultValue={accessTypeOptions.find(v => (!networkInterface.accessType && v.value == '') || (networkInterface.accessType && v.value == networkInterface.accessType))}
                        onChange={props.onNetworkInterfaceProtocolChange}
                    />
                </div>
                <Button variant="danger" onClick={() => props.onNetworkInterfaceRemove(networkInterface.id)} block>
                    <SVG name="remove" className="image-sm v-sub" /> Remove
                </Button>
            </div>
        })}
        <Button variant="success" onClick={() => props.onNetworkInterfaceCreate(device.id)} block>
            <SVG name="plus-square" className="image-sm v-sub" /> Add network interface
        </Button>
    </AsideMenu>);
}
