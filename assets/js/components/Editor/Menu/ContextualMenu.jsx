import React from 'react';
import { ListGroup } from 'react-bootstrap';

export class ContextualMenu extends React.Component
{
    constructor(props) {
        super(props);

        this.state = {
            x: props.x,
            y: props.y,
            show: props.show
        }
    }

    componentDidUpdate(prevProps) {
        if (this.props.x !== prevProps.x) {
            this.setState({x: this.props.x});
        }
        if (this.props.y !== prevProps.y) {
            this.setState({y: this.props.y});
        }
        if (this.props.show !== prevProps.show) {
            this.setState({show: this.props.show});
        }
    }

    render() {
        return (
            <ListGroup
                style={{top: this.state.y, left: this.state.x, display: this.state.show ? 'block' : 'none'}}
                as="ul"
                className={this.props.className ? this.props.className + " editor-contextual-menu" : "editor-contextual-menu"}>
                    {this.props.children}
            </ListGroup>
        );
    }
}

export class Item extends React.Component
{
    render()
    {
        const className = "editor-contextual-menu-option" + (this.props.className ? (" " + this.props.className) : "");

        return (
            <ListGroup.Item as="li" action onClick={this.props.onClick} className={className}>
                {this.props.children}
            </ListGroup.Item>
        );
    }
}

ContextualMenu.Item = Item;

export default ContextualMenu;