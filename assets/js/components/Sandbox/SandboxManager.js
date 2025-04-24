import React, { Component } from 'react';
import { Button} from 'react-bootstrap';
import SandboxList from './SandboxList';

class SandboxManager extends Component {
    constructor(props) {
        super(props);
    }

    render() {
        return (
            <SandboxList devices={this.props.devices} user={this.props.user} labs={this.props.labs}></SandboxList>
        )
    }

}

export default SandboxManager;
