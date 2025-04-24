import React from 'react';
import SandboxListItem from './SandboxListItem';

const SandboxList = (props) => 
{
    console.log("props :");
    console.log(props);
    let devices = props.devices.map(
        (device, index) => <SandboxListItem item={device} itemType={'device'} key={index} index={index} itemsLength={props.devices.length} user={props.user} />
    );

    let labs = props.labs.map(
        (lab, index) => <SandboxListItem item={lab} itemType={'lab'} key={index} index={index} itemsLength={props.labs.length} user={props.user} />
    );

    return (
        <><h2>Devices</h2>
            {devices}
            <h2>Labs</h2>
            {labs}
        </>)
}

export default SandboxList;
