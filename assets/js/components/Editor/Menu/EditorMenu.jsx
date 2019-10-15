import React, { Component } from 'react';
import { Button, ButtonToolbar, OverlayTrigger, Tooltip } from 'react-bootstrap';
import SVG from '../../Display/SVG';

export default class EditorMenu extends Component
{
    constructor(props)
    {
        super(props);

        this.state = {
            fullscreen: false,
        }
    }

    handleCreateDevice = event => {
        this.props.onCreateDeviceRequest();
    }

    handleToggleFullscreen = (e) => {
        this.setState({fullscreen: !this.state.fullscreen});
        this.props.onToggleFullscreen(e);
    }

    render()
    {
        return (<div className="d-flex">
            <ButtonToolbar className="d-flex">
                <Button variant="success" onClick={this.handleCreateDevice}>
                    <span><SVG name="plus-square" className="image-sm v-sub"></SVG> Add device</span>
                </Button>
            </ButtonToolbar>
            <div className="separator flex-grow-1"> </div>
            <ButtonToolbar className="d-flex">
                <Button variant="default" onClick={this.handleToggleFullscreen}>
                    <OverlayTrigger overlay={<Tooltip>Toggle fullscreen</Tooltip>}>
                        <SVG name={this.state.fullscreen ? "screen-normal" : "screen-full"} className="image-sm v-sub"></SVG>
                    </OverlayTrigger>
                </Button>
            </ButtonToolbar>
        </div>);
    }
}