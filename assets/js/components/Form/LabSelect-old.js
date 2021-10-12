import React, { useState,Component, useEffect } from 'react';
import { components } from 'react-select';
import ASyncSelect from 'react-select/async';
import Select from "react-select";
import Remotelabz from '../API';

function LabSelect(props = {lab: {}}) {
  //const [labs,setLab] = useState(props);
  //const labs = Array.from(props).map( item => JSON.parse(props))
  const labs = Object.values(props)
  
  function options(labs) {
    labs.map( (item,i) => {
      return (
        <option key={i} value={item.id}>{item.name}</option>
      )
    }
    )
  };

  useEffect( () => {
    console.log("props",props)
    console.log("labs",labs)  
  }

  )

   return (
    <div className="d-flex flex-column">
      <Select
        className='react-select-container'
        classNamePrefix="react-select"
       > <Option>Test</Option>
       <Option>Test2</Option>
         {options(labs)}
      </Select>
     </div>
   );
 };

 export default LabSelect;