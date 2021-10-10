import React, { useState,Component, useEffect } from 'react';
import { components } from 'react-select';
import ASyncSelect from 'react-select/async';
import Select from "react-select";
import Remotelabz from '../API';


function LabSelect(props) {
  /*const options = [
      { value: "The Crownlands" },
      { value: "Iron Islands" },
      { value: "The North" },
      { value: "The Reach" },
      { value: "The Riverlands" },
      { value: "The Vale" },
      { value: "The Westerlands" },
      { value: "The Stormlands" }
  ];*/
  const options = useState(props);
  const [region, setRegion] = useState();
  const [currentCountry, setCurrentCountry] = useState(null);
  const onchangeSelect = (item) => {
  setCurrentCountry(null);
  setRegion(item);
  };

  useEffect( () => {
    console.log("options".options)
  }

  )

   return (
    <div className="d-flex flex-column">
      <Select
        className='react-select-container'
        classNamePrefix="react-select"
        isMulti
          value={region}
          onChange={onchangeSelect}
          options={options}
          getOptionValue={(option) => option.value}
          getOptionLabel={(option) => option.value}
       /> 
     </div>
   );
 };

 export default LabSelect;