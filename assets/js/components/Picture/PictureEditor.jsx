import React from 'react';
import Noty from 'noty';
import API from '../../api';
import Cropper from 'cropperjs';
import { ButtonToolbar, ButtonGroup, Button } from 'react-bootstrap';

const api = API.getInstance();

export default class PictureEditor extends React.Component {
    constructor(props) {
        super(props);

        this.state = {
		    picture: null,
            isReady: false
        }
    }

    componentDidMount = () => {
        this.props.setUpload(this.upload);

        var reader = new FileReader();

        reader.onload = (event) => {
            this.setState({
		        picture: event.target.result
            });
	    }
        reader.readAsDataURL(this.props.file);
    }

    componentWillUnmount() {
        if (this.cropper) {
            this.cropper.destroy();
            this.cropper = null;
        }
    }

    initCropper = () => {
        if (!this.imageElement) return;
        if (this.cropper) {
            this.cropper.destroy();
        }

        this.setState({ isReady: false });

        this.cropper = new Cropper(this.imageElement, {
            viewMode: 1,
            dragMode: "move",
            aspectRatio: 1,
            guides: false,
            center: false,
            scalable: false,
            cropBoxMovable: false,
            cropBoxResizable: false,
		    modal: false,
            ready: () => {
                this.setState({ isReady: true });
            },
        });
	}

    upload = () => {
        this.cropper.getCroppedCanvas({
            minWidth: 256,
            minHeight: 256,
            maxWidth: 4096,
            maxHeight: 4096,
            fillColor: '#fff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
          }).toBlob((blob) => {
            const formData = new FormData();
            formData.append('picture', blob);

            // Use `jQuery.ajax` method
            api.post(this.props.endpoint, formData)
            .then(() => {
                console.log('Upload success');
                this.props.uploadCallback();
            })
            .catch(() => {
                console.log('Upload failed');

                new Noty({
                    type: 'error',
                    text: 'Error uploading your new profile picture. Please try again.',
                    timeout: 5000
                }).show();
            });
        });
    }

    zoomIn = () => {
        this.cropper.zoom(0.1);
    }

    zoomOut = () => {
        this.cropper.zoom(-0.1);
    }

    render() {
        if (!this.props.file) {
            return null;
        }

        return (<>
	    <div className="cropper-div">
	        {this.state.picture && (<img ref={imageElement => this.imageElement = imageElement} src={this.state.picture} onLoad={this.initCropper} />)}

            <ButtonToolbar className="mt-2 justify-content-center">
                <ButtonGroup>
                    <Button variant="info" onClick={this.zoomOut}><i className="fa fa-search-minus" aria-hidden="true"></i></Button>
                    <Button variant="info" onClick={this.zoomIn}><i className="fa fa-search-plus" aria-hidden="true"></i></Button>
                </ButtonGroup>
            </ButtonToolbar>
        </div>
	</>);
    }
}