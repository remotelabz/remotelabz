import React, { Component } from 'react';
import Cropper from 'cropperjs';

export default class PictureEditor extends Component {
    constructor(props) {
        super(props);

        this.state = {
            isReady: false
        }
    }

    componentDidMount() {
        var reader = new FileReader();
        const parent = this;

        reader.onload = function(event) {
            parent.picture = event.target.result;
            parent.setState({
                isReady: true
            })

            parent.imageElement.addEventListener('ready', function () {
                const containerData = this.cropper.getData();

                this.cropper.zoomTo(0.000001, {
                    x: containerData.width / 2,
                    y: containerData.height / 2,
                });
            });

            parent.cropper = new Cropper(parent.imageElement, {
                viewMode: 1,
                dragMode: "move",
                aspectRatio: 1,
                guides: false,
                center: false,
                scalable: false,
                cropBoxMovable: false,
                cropBoxResizable: false
            });
        }

        reader.readAsDataURL(this.props.file);
    }

    upload() {
        const parent = this;

        this.cropper.getCroppedCanvas({
            minWidth: 256,
            minHeight: 256,
            maxWidth: 4096,
            maxHeight: 4096,
            fillColor: '#fff',
            imageSmoothingEnabled: false,
            imageSmoothingQuality: 'high',
          }).toBlob((blob) => {
            const formData = new FormData();
          
            formData.append('picture', blob);
          
            // Use `jQuery.ajax` method
            $.ajax('/profile/picture', {
              method: "POST",
              data: formData,
              processData: false,
              contentType: false,
              success() {
                console.log('Upload success');
                parent.props.uploadCallback();
              },
              error() {
                console.log('Upload failed');
              },
            });
        });
    }

    zoomIn() {
        this.cropper.zoom(0.1);
    }

    zoomOut() {
        this.cropper.zoom(-0.1);
    }

    render() {
        let zoomButtons; 

        if (!this.props.file) {
            return null;
        }

        if (!this.state.isReady) {
            return null;
        } else {
            zoomButtons = (
                <div>
                    <button className="btn btn-info" onClick={ this.zoomIn.bind(this) }></button>
                    <button className="btn btn-info" onClick={ this.zoomOut.bind(this) }></button>
                </div>
            );
        }

        return (
            <div>
                <img ref={imageElement => this.imageElement = imageElement} src={this.picture} />
                { zoomButtons }
                <button className="btn btn-info" onClick={ this.upload.bind(this) }>Upload picture</button>
            </div>
        );
    }
}