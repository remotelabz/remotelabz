import React, { Component } from 'react';
import PictureEditor from './PictureEditor';
import Modal from '../Display/Modal';
import Button from '../Display/Button';
import Noty from 'noty';

export default class ProfilePictureUploader extends Component {
    constructor(props) {
        super(props);

        this.state = {
            requestedChange: false
        }
    }

    onChangeFile(event) {
        event.stopPropagation();
        event.preventDefault();
        this.file = event.target.files[0];
        if (this.file) {
            this.setState({
                requestedChange: true
            })
        }
    }

    uploadCallback() {
        $('#profilePictureModal').modal('toggle');
        this.upload.files = null;
        
        new Noty({
            type: 'success',
            text: 'Profile picture has been updated.'
        }).show();
    }

    onModalClose() {
        this.setState({
            requestedChange: false
        });
        this.upload.value = null;
    }

    render() {
        let modal;

        if (this.state.requestedChange) {
            modal = (
                <Modal id="profilePictureModal" title="Upload an image" onClose={this.onModalClose.bind(this)}>
                    <PictureEditor file={this.file} uploadCallback={this.uploadCallback.bind(this)}></PictureEditor>
                </Modal>
            )
        } else {
            modal = null;
        }

        return (
            <div>
                <div className="file-upload">
                    <button className="btn btn-default" onClick={ ()=>{ this.upload.click() } }>Choose file</button>
                    <input
                        type="file"
                        id="file"
                        ref={ (ref) => this.upload = ref }
                        style={{display: "none"}}
                        onChange={this.onChangeFile.bind(this)}
                    />
                </div>
                { modal }
            </div>
        )
    }
}