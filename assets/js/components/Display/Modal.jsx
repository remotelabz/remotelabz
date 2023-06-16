import React, { Component } from 'react';

class Modal extends Component {
    componentDidMount() {
        const parent = this;
        var modalName = this.props.id;
        $('#' + modalName).modal('toggle');

        $('#' + modalName).on('hidden.bs.modal', function (e) {
            parent.props.onClose();
        })
    }

    render() {
        return (
            <div id={this.props.id} className="modal fade" tabIndex="-1" role="dialog" aria-hidden="true">
                <div className="modal-dialog modal-sm">
                    <div className="modal-content">
                        <div className="modal-header">
                            <h5 className="modal-title">{this.props.title}</h5>
                            <button type="button" className="close" data-dismiss="modal">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div className="modal-body">
                            {this.props.children}
                        </div>
                        <div className="modal-footer"></div>
                    </div>
                </div>
            </div>
        )
    }
}

Modal.defaultProps = {
    onClose: function () {}
}

export default Modal;