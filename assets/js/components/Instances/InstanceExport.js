import React, { Component } from 'react';
import { Button} from 'react-bootstrap';

class InstanceExport extends Component {
    constructor(props) {
        super(props);

        this.exportName = React.createRef();
        //console.log("export");
        //console.log(props.deviceInstance.device);
    }

    handleSubmit() {
        this.props.exportTemplate(this.props.instance, this.exportName.current.value);
    }

    render() {
        return(
            <div className="d-flex align-items-center justify-content-center">
                <label>
                    New Name
                </label> 
                <input type="text" ref={this.exportName}/> 
                <Button 
                    variant="primary" 
                    onClick={() => this.handleSubmit()}
                > 
                {this.props.type == "device" && "Export Device"}
                {this.props.type == "lab" && "Export Lab"}
                </Button>
            </div>
        )
    }
}

export default InstanceExport;