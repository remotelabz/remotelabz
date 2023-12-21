import React, { useState, useEffect} from 'react';
import Noty from 'noty';
import Remotelabz from '../API';
import {ListGroup, ListGroupItem, Button, Modal} from 'react-bootstrap';
import { ajax } from 'jquery';
import { extension } from 'showdown';

export default function DownloadLxcDevice(props) {
    const [osList, setOsList] = useState();
    const [osChoice, setOsChoice] = useState();
    const [versionList, setVersionList] = useState();
    const [versionChoice, setVersionChoice] = useState();
    const [updateChoice, setUpdateChoice] = useState();
    const architecture = 'amd64/';

    useEffect(()=>{
        var list = [];
        for(let i=0; i< Object.keys(props).length; i++) {
            list.push(props[i])
        }
        setOsList(list.map((os)=>{
            return (<option value={`${os.toLowerCase()}/`}>{os}</option>)
        }));
    }, [])

    function onOsChange() {
        var os = document.getElementById("os").value;
        setOsChoice(os);
        let data = {"os": os};
        $.ajax({
            type: "POST",
            url: `/api/devices/lxc_params`,
            dataType: 'json',
            data: JSON.stringify(data)
        }).then((response)=> {
            setVersionList(response.map((version)=> {
                return (<option value={`${version}/`}>{version}</option>)
            }));
        })
    }

    /*function onVersionChange() {
        var version = document.getElementById("version").value;
        setVersionChoice(version);
        let data = {"os": osChoice, "version": version};
        $.ajax({
            type: "POST",
            url: `/api/devices/lxc_params`,
            dataType: 'json',
            data: JSON.stringify(data)
        }).then((response)=> {
            setUpdateChoice(response);
        })
    }*/

    function handleSubmit() {
        //let name = document.getElementById("name").value;
        let osValue = document.getElementById("os").value;
        let versionValue = document.getElementById("version").value;
        let os = osValue.substring(0, osValue.length -1);
        let version = versionValue.substring(0, versionValue.length -1);

        let data = { 'os': os, 'version': version}
        $.ajax({
            type: "POST",
            url: `/api/devices/lxc`,
            dataType: 'json',
            data: JSON.stringify(data)
        }).then((response)=>{
            window.location.href='/admin/devices/new?os='+ response.os + "&model="+response.model
        })
    }
    
    return (
    <div>
        <div className="form-group row">
            <label className="col-form-label col-sm-2 required text-right" for="oslist">Operating system</label>
            <div className='col-sm-10'>
                <select id="os" className='form-control'name="oslist" onChange={onOsChange}>{osList}</select>
                
                {//<select id="os" className='form-control'name="oslist">
                   // <option value="almalinux/">Almalinux</option>
                //</select>
                }
            </div>
        </div>
        <div className="form-group row">
            <label className="col-form-label col-sm-2 required text-right" for="versionlist">Distribution</label>
            <div className='col-sm-10'>
                <select id="version" className='form-control' name="versionlist">{versionList}</select>
                
                {//<select id="version" className='form-control' name="versionlist">
                    //<option value="current/">current</option>
                //</select>
                }
            </div>
        </div>
        <div className='form-actions'>
            <input type="submit" onClick={handleSubmit} className="btn btn-success" value="Submit"/>
        </div>
    </div>
    );
}