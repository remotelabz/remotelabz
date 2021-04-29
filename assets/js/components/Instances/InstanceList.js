import React from 'react';
import InstanceListItem from './InstanceListItem';

const InstanceList = (props) => props.instances.map(
    (deviceInstance, index) => <InstanceListItem instance={deviceInstance} key={index} {...props} />
);

export default InstanceList;