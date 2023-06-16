import React, { Component } from 'react';
import PictureEditor from './PictureEditor';
import { Modal, Button } from 'react-bootstrap';
import Noty from 'noty';

export default class ProfilePictureUploader extends Component {
    constructor(props) {
        super(props);

        this.state = {
            showModal: false,
            uploading: false,
            //guid: Date.now(),
        }
    }

    onChangeFile = (event) => {
        event.stopPropagation();
        event.preventDefault();
        this.file = event.target.files[0];
        if (this.file) {
            this.setState({
                showModal: true
            });
        }
    }

    uploadCallback = () => {
        this.setState({
            showModal: false,
            guid: new Date(),
        });
        this.upload.files = null;
        
        new Noty({
            type: 'success',
            text: 'Profile picture has been updated.',
            timeout: 5000
        }).show();
    }

    onModalClose = () => {
        this.upload.value = null;
    }

    render() {

        return (
            <div>
                <img src={"/profile/picture?size=160&hash=" + Date.now()} className="img-xl rounded-circle mr-4 float-left"></img>
                <h5>Upload new avatar</h5>
                <p className="text-muted">The maximum file size allowed is 200KB.</p>
                <div className="file-upload">
                    <Button className="btn btn-default" onClick={ () => {this.upload.click()} }>Choose file</Button>
                    <input
                        type="file"
                        id="file"
                        ref={ (ref) => this.upload = ref }
                        style={{display: "none"}}
                        onChange={this.onChangeFile}
                    />
                </div>
                <Modal id="profilePictureModal" title="Upload an image" size="lg" show={this.state.showModal} onHide={this.onModalClose}>
                    <Modal.Header closeButton>
                        <Modal.Title>Upload a profile picture</Modal.Title>
                    </Modal.Header>
                    <Modal.Body>
                        <PictureEditor setUpload={click => this.onModalValid = click} file={this.file} uploadCallback={this.uploadCallback} endpoint="/profile/picture" />
                    </Modal.Body>
                    <Modal.Footer>
                        <Button variant="info" onClick={() => this.onModalValid()}>Set new profile picture</Button>
                    </Modal.Footer>
                </Modal>
            </div>
        )
    }
}