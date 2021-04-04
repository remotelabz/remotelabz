import React, { Component } from 'react';
import { Button, ButtonToolbar, ButtonGroup, OverlayTrigger, Tooltip } from 'react-bootstrap';
import SVG from '../../Display/SVG';

export default class Menu extends Component
{
    constructor(props)
    {
        super(props);

        this.state = {
            fullscreen: false,
        }
    }

    handleCreateDevice = () => {
        this.props.onCreateDeviceRequest();
    }

    handleToggleFullscreen = (e) => {
        this.setState({fullscreen: !this.state.fullscreen});
        this.props.onToggleFullscreen(e);
    }

    handleZoomIn = () => this.props.onZoomIn();

    handleZoomOut = () => this.props.onZoomOut();

    handleLabEdit = () => this.props.onLabEditClick();

    render()
    {
        return (
            <div className="d-flex">
                <ButtonToolbar className="d-flex">
                    <Button variant="success" onClick={this.handleCreateDevice}>
                        <span><SVG name="plus-square" className="image-sm v-sub"></SVG> Add device</span>
                    </Button>

                    <ButtonGroup className="ml-3">
                        <OverlayTrigger placement="bottom" overlay={<Tooltip>Zoom out</Tooltip>}>
                            <Button variant="default" onClick={this.handleZoomOut}>
                                <span><i className="fa fa-search-minus" aria-hidden="true"></i></span>
                            </Button>
                        </OverlayTrigger>

                        <OverlayTrigger placement="bottom" overlay={<Tooltip>Zoom in</Tooltip>}>
                            <Button variant="default" onClick={this.handleZoomIn}>
                                <span><i className="fa fa-search-plus" aria-hidden="true"></i></span>
                            </Button>
                        </OverlayTrigger>
                    </ButtonGroup>

                    {/* <OverlayTrigger placement="bottom" overlay={<Tooltip>Lab options</Tooltip>}>
                        <Button variant="default" className="ml-3" onClick={this.handleLabEdit}>
                            <span><SVG name="settings" className="image-sm v-sub"></SVG></span>
                        </Button>
                    </OverlayTrigger> */}
                </ButtonToolbar>
                <div className="separator flex-grow-1"> </div>
                <ButtonToolbar className="d-flex">
                    <OverlayTrigger placement="bottom" overlay={<Tooltip>Toggle fullscreen</Tooltip>}>
                        <Button variant="default" onClick={this.handleToggleFullscreen}>
                            <SVG name={this.state.fullscreen ? "minimize" : "maximize"} className="image-sm v-sub"></SVG>
                        </Button>
                    </OverlayTrigger>
                </ButtonToolbar>
            </div>
        );
    }
}