import React, { Component } from 'react';
import { ListGroup } from 'react-bootstrap';

export default class EditorContextualMenu extends Component
{
    constructor(props)
    {
        super(props);

        this.state = {
            x: props.x,
            y: props.y,
        }
    }

    handleDeleteItem = () => {
        console.log("User clicked on delete.");
    }

    render()
    {
        return (
            <ListGroup style={{top: this.state.y, left: this.state.x}} as="ul" className="editor-contextual-menu">
                <ListGroup.Item as="li" action className="editor-contextual-menu-option" onClick={this.handleDeleteItem}>Delete</ListGroup.Item>
            </ListGroup>
        );
    }
}