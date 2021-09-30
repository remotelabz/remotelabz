import React from 'react';
import InstanceListItem from './InstanceListItem';

const InstanceList = (props) => {
    
    return props.instances.map(
        (deviceInstance, index) => <InstanceListItem instance={deviceInstance} key={index} {...props} />
    );
}

export default InstanceList;