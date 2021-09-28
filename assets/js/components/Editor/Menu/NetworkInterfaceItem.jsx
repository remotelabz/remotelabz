import React, {useState} from 'react';
import Noty from 'noty';
import Remotelabz from '../../API';
import SVG from '../../Display/SVG';
import { Button } from 'react-bootstrap';
import NetworkInterfaceForm from './../Form/NetworkInterfaceForm';

export default function NetworkInterfaceItem(props) {
    const [networkInterface, setNetworkInterface] = useState(props.networkInterface);

    /** @param {{value: string, label: string, id: number}} option */
    const onNetworkInterfaceUpdate = async networkInterface => {
        //console.log("onNetworkInterfaceUpdate in file NetworkInterfaceItem", networkInterface);
        const response = await Remotelabz.networkInterfaces.update(networkInterface.id, networkInterface);
        setNetworkInterface(response.data);
        new Noty({ type: 'success', text: 'NIC has been updated.' }).show();
    }
    //console.log("NetworkInterfaceItem props.networkInterface", props.networkInterface);
    //console.log("NetworkInterfaceItem networkInterface variable", networkInterface);

    return (
        <div key={networkInterface.uuid} className="device-network-interface-item px-3 py-3 mb-3">
            <h4 className="mb-2">NIC #{props.index + 1}</h4>
            <NetworkInterfaceForm onSubmit={onNetworkInterfaceUpdate} networkInterface={networkInterface} />
            <hr />
            <Button variant="danger" onClick={() => props.onNetworkInterfaceDelete(networkInterface.id)} block>
                <SVG name="remove" className="image-sm v-sub" /> Remove
            </Button>
        </div>
    );
}