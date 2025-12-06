import { ToastContainer } from 'react-toastify';
import React, { Component } from 'react';
import { Button} from 'react-bootstrap';
import SandboxList from './SandboxList';
import { createRoot } from 'react-dom/client';

class SandboxManager extends Component {
    constructor(props) {
        //console.log("props in Sandbox manager",props);

        super(props);
    }
    
    render() {
        return (
			<>
            <SandboxList devices={this.props.devices} user={this.props.user} labs={this.props.labs}></SandboxList>
			<ToastContainer
				position="top-right"
				autoClose={5000}
				hideProgressBar={false}
				closeOnClick
				pauseOnHover
				draggable
				pauseOnFocusLoss={false}
			/>
			</>
        )
    }

}

export default SandboxManager;
