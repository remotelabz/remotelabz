import React, { useState } from 'react';
import SandboxListItem from './SandboxListItem';

const SandboxList = (props) => 
{
    //console.log("SanboxList props :",props);
    const [isAnyLoading, setIsAnyLoading] = useState(false);
    
    let devices = props.devices.map(
        (device, index) => (
            <SandboxListItem 
                item={device} 
                itemType={'device'} 
                key={index} 
                index={index} 
                itemsLength={props.devices.length} 
                user={props.user}
                isAnyLoading={isAnyLoading}
                setIsAnyLoading={setIsAnyLoading}
            />
        )
    );

    let labs = props.labs.map(
        (lab, index) => (
            <SandboxListItem 
                item={lab} 
                itemType={'lab'} 
                key={index} 
                index={index} 
                itemsLength={props.labs.length} 
                user={props.user}
                isAnyLoading={isAnyLoading}
                setIsAnyLoading={setIsAnyLoading}
            />
        )
    );

    return (
        <><h2>Devices</h2>
            {devices}
            <h2>Labs</h2>
            <i class="fa fa-info-circle text-info">&nbsp;</i>Once a lab has been exported, you can import it into your labs using the “Import Lab” button 
            {labs}
        </>)
}

export default SandboxList;
