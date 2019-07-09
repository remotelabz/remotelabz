import React, { Component } from 'react';
import PropTypes from 'prop-types';

class Button extends Component {
    onClickCallback() {
        this.props.onClick();
    }

    render() {
        return (
            <button id={this.props.id} className={ this.props.className } onClick={this.onClickCallback.bind(this)}>{this.props.children}</button>
        )
    }
}

Button.defaultProps = {
    id: '',
    className: '',
    onClick: function () {}
}

Button.propTypes = {
    id: PropTypes.string,
    className: PropTypes.string,
    onClick: PropTypes.func
}

export default Button;