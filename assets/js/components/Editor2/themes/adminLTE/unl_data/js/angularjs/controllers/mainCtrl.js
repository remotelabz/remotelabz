function mainController($scope, $http, $location, $window, $uibModal, $log, $rootScope, FileUploader, focus) {
		$rootScope.openLaba=false;
		$scope.testAUTH("/main"); //TEST AUTH
		//Default variables ///START
		console.log('Current user position: '+$rootScope.folder)
		$scope.path= ($rootScope.folder === undefined || $rootScope.folder == '') ? '/' :  $rootScope.folder;
		$scope.newElementName='';
		$scope.newElementToggle=false;
		$scope.fileSelected=false;
		$scope.allCheckedFlag=false;
		$scope.blockButtons=false;
		$scope.blockButtonsClass='';
		$scope.fileManagerItem=[];
		$scope.checkboxArray=[];
		$scope.fileOrder='file';
		//Default variables ///END
		
		//console.log('here')
		
		$scope.falseForSelAll = function(){
			$scope.allCheckedFlag=false;
		}
		$scope.toggleOrder = function(){
			if ( $scope.fileOrder == 'umtime' ) 
				$scope.fileOrder = 'file'
			else
				$scope.fileOrder = 'umtime'
		}	


		$('body').removeClass().addClass('hold-transition skin-blue layout-top-nav');
		//Draw current position //START
		$scope.currentPosition = function(){
			var tempArray=$scope.path.split('/');
			var tempPathArray=[]
			tempArray[0]='root';
			tempPathArray[0]="/";
			if (tempArray[1]==="") {tempArray.splice( 1, 1 );}
			else {
				for (i = 1; i < tempArray.length; i++) {
					tempVal='/'+tempArray[i];
					tempPathArray[i] = (i-1 === 0) ? tempVal : tempPathArray[i-1]+tempVal;
				}
			}
			//console.log(tempArray)
			//console.log(tempPathArray)
			$scope.splitPath=tempArray;
			$scope.splitPathArray=tempPathArray;
			
			//$scope.splitPathArray=tempArray.length;
		}
		$scope.currentPosition();
		//Draw current position //END
		////////////////////////////////
		//Drawing files tree ///START
		$scope.fileMngDraw = function (path,folder){
		$scope.path=path;
		if (folder !== undefined){
			$scope.fileManagerItem['Fo_'+folder]['img']=true;
		}
		$http.get('/api/folders'+path).then(
			function successCallback(response) {
				$scope.checkboxArray=[];
				$scope.fileManagerItem=[];
				$scope.rootDir = response.data.data;
				$scope.currentPosition();
				$.unblockUI();
				//console.log($scope.rootDir)
				// setTimeout(function(){
				// 	$scope.getLabInfo($scope.rootDir.labs[0].path, $scope.rootDir.labs[0].file);
				// }, 1000)
			}, 
			function errorCallback(response) {
				$.unblockUI();
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");}	
		);
		}
		$scope.fileMngDraw($scope.path);
		//Drawing files tree ///END
		////////////////////////////////////
		//Get information about lab ///START
		$scope.getLabInfo = function (file,name){
		var path = ($scope.path === '/') ? $scope.path :  $scope.path+'/';
		if (name !== undefined) $scope.fileManagerItem['Fi_'+name].img=true;
		$http.get('/api/labs'+file).then(
			function successCallback(response) {
				$scope.labInfo = response.data.data;
				$scope.fullPathToFile=file;
				$scope.selectedLab=name;
				if (name !== undefined) $scope.fileManagerItem['Fi_'+name].img=false;
				//console.log($scope.labInfo)
				$scope.fileSelected=true;
				$scope.previewFun( ($scope.path == '/') ? $scope.path+$scope.labInfo.name+'.unl' : $scope.path+'/'+$scope.labInfo.name+'.unl' )
			}, 
			function errorCallback(response) {
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");
				if (response.status==400) {toastr["error"](response.data.message, "Error"); $scope.blockButtons=false; $scope.blockButtonsClass='';return;}
			}	
		);
		}
		//Get information about lab ///END
		////////////////////////////////////////////////
		//Toggle view for input file/folder creations ///START
		$scope.elementToggleFun = function (thatCreate){
			$scope.hideAllEdit()
			if (!$scope.newElementToggle) {$scope.newElementToggle=true; focus('foCreate'); $scope.thatCreate = thatCreate; return;}
			if ($scope.thatCreate == thatCreate && $scope.newElementToggle) {$scope.newElementToggle = false;}
			if (!$scope.newElementToggle) $scope.thatCreate = ''; 
			$scope.thatCreate = thatCreate;
		}
		//Toggle view for input file/folder creations ///END
		///////////////////////////////////////////////////
		//Create NEW Element Folder OR Lab //START
		$scope.createNewElement = function (elementType){	
			if ($scope.newElementName=='') return;
			
			//Create NEW Folder //START
			if (elementType == 'Folder'){
			$scope.blockButtons=true;
			$scope.blockButtonsClass='m-progress';
			$scope.newElementName = $scope.newElementName.replace(/[\',#,$,@,\",\\,/,%,\*,\,,\.,(,),:,;,^,&,\[,\],\|]/g, '');
			//$scope.newElementName = $scope.newElementName.replace(/[\s]+/g, '_');
			$http({
			method: 'POST',
			url: '/api/folders',
			data: {"path":$scope.path,"name":$scope.newElementName}})
				.then(
				function successCallback(response) {
					
					$scope.fileMngDraw($scope.path);
					$scope.newElementToggle = false;
					$scope.newElementName='';
					$scope.blockButtons=false;
					$scope.blockButtonsClass='';
				}, 
				function errorCallback(response) {
					//console.log(response)
					if (response.status==400) {toastr["error"]("Name already exist", "Error"); $scope.blockButtons=false; $scope.blockButtonsClass='';return;}
					console.log("Unknown Error. Why did API doesn't respond?");
					$location.path("/login");
				}
			);
			}
			//Create NEW Folder //END
			/////////////////////////
			//Create NEW Lab //START
			if (elementType == 'File'){
			$scope.newElementName = $scope.newElementName.replace(/[\',#,$,@,\",\\,/,%,\*,\,,\.,(,),:,;,^,&,\[,\],\|]/g, '');
			//$scope.newElementName = $scope.newElementName.replace(/[\s]+/g, '_');
				$scope.openModal('addfile');
			}
			//Create NEW Lab //END
			////////////////////////
		}
                //Clone Lab//START

                $scope.cloneElement = function (elementName,event){
		d = new Date();
                console.log('clone requested for '+$scope.path+'/'+elementName.value + ' ' + d.getTime());
                form_data = {};
                form_data['name'] = elementName.value.slice(0,-4)+'_'+d.getTime();
                form_data['source'] = $scope.path + '/' + elementName.value;
                $http({
                method: 'POST',
                url: '/api/labs',
                data: form_data})
                        .then(
                                function successcallback(response) {
                                //console.log(response)
                                //$scope.filemngdraw($scope.path);
				$scope.fileMngDraw($scope.path);
				//$location.path("/login");
                                },
                                function errorcallback(response) {
                                //console.log(response)
                                console.log("unknown error. why did api doesn't respond?");
                                $location.path("/login");
                                }
                        );
                
                
                event.stopPropagation();
                }

                //clone lab//end


		//Create NEW Element Folder OR Lab //END
		///////////////////////////////////////
		//Delete selected elements //START
		$scope.deleteElement = function (elementName,thatis,hide){
			$scope.hideAllEdit()	
			var tempVal = (hide === undefined) ? false :  true;
			//Delete folder//START
			if (thatis == 'Folder'){
				if (tempVal) if (!confirm('Are you sure you want to delete this folder: ' + elementName)) return;
				console.log('deleting folder '+elementName)
				$http({
				method: 'DELETE',
				url: '/api/folders'+elementName+'\ '})
					.then(
					function successCallback(response) {
						//console.log(response)
						$scope.fileMngDraw($scope.path);
					}, 
					function errorCallback(response) {
						//console.log(response)
						console.log("Unknown Error. Why did API doesn't respond?");
						$location.path("/login");
					}
				);
			}
			//Delete folder//END
			////////////////////
			//Delete file//START
			if (thatis == 'File'){
				if (tempVal) if (!confirm('Are you sure you want to delete this lab: ' + elementName)) return;
				console.log('delete file')
				console.log(elementName)
				$http({
				method: 'DELETE',
				url: '/api/labs'+elementName})
					.then(
					function successCallback(response) {
						//console.log(response)
						$scope.fileSelected = (hide === undefined) ? $scope.fileSelected :  false;
						$scope.fileMngDraw($scope.path);
					}, 
					function errorCallback(response) {
						//console.log(response)
						console.log("Unknown Error. Why did API doesn't respond?");
						$location.path("/login");
					}
				);
			}
			//Delete file//END
			//$scope.fileMngDraw($scope.path); //recreate tree
		}
		//Delete selected elements //END
		//////////////////////////////////////////////////
		//Delete ALL selected elements //START
		$scope.deleteALLElement = function (){
			var folderArray=[];
			var lastFolder='';
			var lastFile='';
			var fileArray=[];
			for (var key in $scope.checkboxArray){
				//console.log($scope.fileManagerItem[key]);
				if ($scope.checkboxArray[key].checked){
					var itemType = ($scope.checkboxArray[key].type == 'Folder') ? 'Fo_' : 'Fi_';
					if (itemType == 'Fo_'){
						folderArray[key.replace(itemType,'')] = $scope.path
					}
					if (itemType == 'Fi_'){
						fileArray[key.replace(itemType,'')] = $scope.path
					}
				}
			}
			if (ObjectLength(folderArray) == 0 && ObjectLength(fileArray)==0){toastr["warning"]("Please select items to delete", "Warning"); return;}
			var folderCount=1;
			var fileCount=1;
			var tempAllNames='';
			if (ObjectLength(folderArray) > 0)
			for (var foldername in folderArray){
				if (folderCount !== ObjectLength(folderArray) || ObjectLength(fileArray) != 0) {commaChar=',';} else commaChar='';
				tempAllNames += ' '+ foldername+commaChar;
				folderCount++
			}
			commaChar='';
			if (ObjectLength(fileArray) > 0)
			for (var filename in fileArray){
				if (fileCount !== ObjectLength(fileArray)) commaChar=','; else commaChar='';
				tempAllNames += ' '+ filename+commaChar;
				fileCount++
			}
			console.log(tempAllNames)
			if (confirm('Are you sure you want to delete this item: ' + tempAllNames+'?')){
				for (var foldername in folderArray){
					if (folderArray[foldername] != '/') fullpath=folderArray[foldername]+'/'+foldername
					else fullpath='/'+foldername
					$scope.deleteElement(fullpath,'Folder')
				}
				
				for (var filename in fileArray){
					filename = ($scope.path === '/') ? $scope.path+filename :  $scope.path+'/'+filename;
					$scope.deleteElement(filename,'File')
				}
			} else return;
		}
		//Delete ALL selected elements //END
		//////////////////////////////////////////
		//Select all elements //START
		$scope.selectAll = function(){
			if (!$scope.allCheckedFlag){
				for (var key in $scope.checkboxArray){
					//console.log($scope.fileManagerItem[key]);
					$scope.checkboxArray[key].checked = ($scope.checkboxArray[key].name != '..') ? true : false;
				}
				$scope.allCheckedFlag=true;
				return;
			}
			console.log($scope.allCheckedFlag);
			if ($scope.allCheckedFlag){
				$scope.hideAllEdit()
				for (var key in $scope.checkboxArray){
					$scope.checkboxArray[key].checked=false;
				}
				$scope.allCheckedFlag=false;
			}
		}
		//Select all elements //END
		///////////////////////////////////////////
		//Select element by clicking on <td> //START
		$scope.selectElbyTD = function(item){
			//console.log(item)
			if (item.name=='..') return;
			var itemType= (item.type == 'Folder') ? 'Fo_' : 'Fi_';
			//console.log(itemType+item.name)
			//console.log($scope.checkboxArray[itemType+item.name])
			$scope.checkboxArray[itemType+item.name].checked=!$scope.checkboxArray[itemType+item.name].checked;
			$scope.falseForSelAll(); 
			$scope.hideAllEdit();
		}
		//Select element by clicking on <td> //END
		///////////////////////////////////////////////////////
		//Edit element //START
		/////
		$scope.editElementShow = function(){
			console.log($scope.checkboxArray);
			var trueCheckbox=0;
			var tempArray=[];
			for (var key in $scope.checkboxArray){
				console.log($scope.checkboxArray[key].checked)
				if ($scope.checkboxArray[key].checked===true) {
					tempArray['type']=$scope.checkboxArray[key].type;
					tempArray['name']=key;
					trueCheckbox++
				}
			}
			if (trueCheckbox == 0) {toastr["warning"]("Please select item to rename", "Warning"); return;}
			if (trueCheckbox > 1) {toastr["warning"]("You can rename only 1 item", "Warning"); return;}
			var itemType= (tempArray['type'] == 'Folder') ? 'Fo_' : 'Fi_';
			console.log(tempArray['name'])
			console.log($scope.fileManagerItem[tempArray['name']])
			$scope.openRename($scope.fileManagerItem[tempArray['name']])
		}
		$scope.hideAllEdit = function(){
			for (var key in $scope.fileManagerItem){
				//console.log($scope.fileManagerItem[key]);
				$scope.fileManagerItem[key].visibleEdit=false;
				$scope.fileManagerItem[key].value=$scope.fileManagerItem[key].oldvalue;
				$scope.allCheckedFlag=false;
			}
		}
		$scope.uncheck_all = function()
		{
			$(".folder_check").prop("checked", false).trigger("change").trigger("unchecked");
		}

		$scope.openRename = function (item, $event){
			if ($event != undefined) $event.stopPropagation();
			$scope.hideAllEdit()
			console.log(item)
			var itemType= (item.type == 'Folder') ? 'Fo_' : 'Fi_';
			if (itemType == 'Fi_' ) {
				$scope.fileManagerItem[itemType+item.oldvalue].value = $scope.fileManagerItem[itemType+item.oldvalue].value.replace(/.unl$/, "");
			}
			focus(itemType+$scope.fileManagerItem[itemType+item.oldvalue].oldvalue);
			$scope.fileManagerItem[itemType+item.oldvalue].visibleEdit=true;
		}
		$scope.editElementApply = function (item){
			var tempPath = ($scope.path === '/') ? $scope.path :  $scope.path+'/';
			var itemType= (item.type == 'Folder') ? 'Fo_' : 'Fi_';
			console.log($scope.fileManagerItem[itemType+item.oldvalue])
			var tempVal = $scope.fileManagerItem[itemType+item.oldvalue].value;
			tempVal=tempVal.replace(/[\',#,$,\",\\,/,%,\*,\,,\.,!,\(,\[,\],\),\},\{]/g, '')
			//tempVal=tempVal.replace(/[\s]+/g, '_');
			$scope.blockButtons=true;
			$scope.blockButtonsClass='m-progress';
			$scope.fileManagerItem[itemType+item.oldvalue].value=tempVal;
			if ($scope.fileManagerItem[itemType+item.oldvalue].value===$scope.fileManagerItem[itemType+item.oldvalue].oldvalue){
				$scope.hideAllEdit();$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				return;
			}
			if ($scope.fileManagerItem[itemType+item.oldvalue].value===$scope.fileManagerItem[itemType+item.oldvalue].oldvalue.replace(/.unl$/, "")){
				$scope.hideAllEdit();$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				$scope.hideAllEdit();
				return;
			}
			if (itemType=='Fo_'){
				$scope.blockButtons=true;
				$scope.blockButtonsClass='m-progress';
				console.log('Rename folder:'+$scope.fileManagerItem[itemType+item.oldvalue].oldvalue+' to '+$scope.fileManagerItem[itemType+item.oldvalue].value)
				$http({
				method: 'PUT',
				url: '/api/folders'+tempPath+$scope.fileManagerItem[itemType+item.oldvalue].oldvalue,
				data: {"path":tempPath+$scope.fileManagerItem[itemType+item.oldvalue].value}})
					.then(
					function successCallback(response) {
						//console.log(response)
						console.log('Rename successfull')
						$scope.blockButtons=false;
						$scope.blockButtonsClass='';
						$scope.fileMngDraw($scope.path);
					}, 
					function errorCallback(response) {
						console.log(response)
						$scope.blockButtons=false;
						$scope.blockButtonsClass='';
						if (response.status==412 && response.data.status=="unauthorized"){$location.path("/login"); return;}
						console.log('Rename Error'+response.data.message)
						toastr["error"](response.data.message, "Error")
					}
				);
			} else if (itemType=='Fi_'){
				console.log('Rename file:'+$scope.fileManagerItem[itemType+item.oldvalue].oldvalue.replace(/.unl$/, "")+' to '+$scope.fileManagerItem[itemType+item.oldvalue].value)
				
				$http({
				method: 'PUT',
				url: '/api/labs'+tempPath+$scope.fileManagerItem[itemType+item.oldvalue].oldvalue,
				data: {"name": $scope.fileManagerItem[itemType+item.oldvalue].value}})
					.then(
					function successCallback(response) {
						//console.log(response)
						console.log('Rename successfull')
						$scope.blockButtons=false;
						$scope.blockButtonsClass='';
						$scope.fileMngDraw($scope.path);
						if ($scope.fileSelected && $scope.selectedLab==$scope.fileManagerItem[itemType+item.oldvalue].oldvalue){
							$scope.labInfo.name=$scope.fileManagerItem[itemType+item.oldvalue].value
						}
					}, 
					function errorCallback(response) {
						console.log(response)
						$scope.blockButtons=false;
						$scope.blockButtonsClass='';
						if (response.status==412 && response.data.status=="unauthorized"){$location.path("/login"); return;}
						console.log('Rename Error'+response.data.message)
						toastr["error"](response.data.message, "Error")
					}
				);
			}
		}
		//Edit element //END 
		/////////////////////////////////////////////////////
		//Export lab //START
		$scope.exportFiles = function(){
			$(".content-wrapper").append("<div id='progress-loader'><label style='float:left'>Creating archive...</label><div class='loader'></div></div>")
			
			var fileExportArray={};
			var tempPath = ($scope.path === '/') ? $scope.path :  $scope.path+'/';
			var index = 0;
			for (var key in $scope.checkboxArray){
				//console.log($scope.fileManagerItem[key]);
				if ($scope.checkboxArray[key].checked){
					var itemType = ($scope.checkboxArray[key].type == 'Folder') ? 'Fo_' : 'Fi_';
					if (itemType == 'Fo_'){
						fileExportArray['"'+index+'"'] = tempPath+key.replace(itemType,'')
						index++
					}
					if (itemType == 'Fi_'){
						fileExportArray['"'+index+'"'] = tempPath+key.replace(itemType,'')
						index++
					}
				}
			}
			if (ObjectLength(fileExportArray) == 0){toastr["warning"]("Please select items to export", "Warning"); return;}
			fileExportArray['path']=$scope.path;
			$http({
				method: 'POST',
				url: '/api/export',
				data: fileExportArray})
					.then(
					function successCallback(response) {
						$("#progress-loader").remove()
						console.log(response.data.data)
						var a         = document.createElement('a');
							a.href        = response.data.data;
							a.target      = '_blank';
							a.download    = response.data.data
							document.body.appendChild(a);
							a.click();
					}, 
					function errorCallback(response) {
						$("#progress-loader").remove()
						console.log(response)
						console.log("Unknown Error. Why did API doesn't respond?");
						//$location.path("/login");
					}
			);
		}
		//Export lab //END
		//////////////////////////////////////////
		///Import lab //START
		var uploader = $scope.uploader = new FileUploader({
	        url: '/api/import',
			//autoUpload : true,
			//removeAfterUpload : true
	    });
		
			$scope.testFun = function(){
			console.log(uploader.queue)}
		
			$scope.selectOneFileUplad = function(){
				$('#oneFileUploadInput').click();
			}
			$scope.fileNameChanged = function(){
			//console.log('here')
			console.log(uploader.queue)
			//console.log($scope.uploader)
			uploader.onBeforeUploadItem = function(item) {
				//console.info('onBeforeUploadItem', item);
				item.formData.push({'path': $scope.path});
			};
			uploader.onSuccessItem = function(fileItem, response, status, headers) {
				//console.info('onSuccessItem', fileItem, response, status, headers);
				$scope.fileMngDraw($scope.path)
			};
			uploader.onCompleteItem = function(fileItem, response, status, headers) {
				$scope.fileMngDraw($scope.path)
				//console.info('onCompleteItem', fileItem, response, status, headers);
			};
			uploader.onErrorItem = function(fileItem, response, status, headers) {
				//console.info('onErrorItem', fileItem, response, status, headers);
				if (status===400) toastr["error"](response.message, "Error");
			};
		}
		///Import lab //END
		//////////////////////////////////////////
		///Move to function ///START
		$scope.moveto = function(){
			$scope.folderArrayToMove=[];
			$scope.fileArrayToMove=[];
			var fo=0;
			var fi=0;
			for (var key in $scope.checkboxArray){
				//console.log($scope.fileManagerItem[key]);
				if ($scope.checkboxArray[key].checked){
					var itemType = ($scope.checkboxArray[key].type == 'Folder') ? 'Fo_' : 'Fi_';
					if (itemType == 'Fo_'){
						$scope.folderArrayToMove[fo] = key.replace(itemType,'')
						fo++
					}
					if (itemType == 'Fi_'){
						$scope.fileArrayToMove[fi] = key.replace(itemType,'')
						fi++
					}
				}
			}
			if (ObjectLength($scope.folderArrayToMove) == 0 && ObjectLength($scope.fileArrayToMove)==0){toastr["warning"]("Please select items to move", "Warning"); return;}
			$scope.pathBeforeMove=$scope.path;
			$scope.openModal('moveto');
		}
		///Move to function ///END
		/////////////////////////////////////
		//Open Lab //START
		$scope.labopen = function(labname){
			$rootScope.lab=labname;
			$location.path('/lab')
			console.log('Open lab: '+$rootScope.lab)
		}
		//Open Lab //END
		//Open Lagacy LAB//START
		$scope.legacylabopen = function(labname){
			$http.get('/api/labs'+labname+'/topology');
			$window.location.href = "legacy"+labname+"/topology" ;
		}
		//Open Lagacy LAB//END
		///////////////////////////////
		//More controllers //START
		ModalCtrl($scope, $uibModal, $log)
		labViewCtrl ($scope)
		//More controllers //END
		//var testString='123123\'/\ \%\#\s$123123 21*3123';
		//console.log(testString.replace(/[\',#,$,\",\\,/,%,\*,\,,\.]/g, ''))
		
		
		
		
		
	/////////////////////////////////////////////	
	////////////////////////////////////////////
	///////PREVIEW FUNCTIONS////////////////START
	/////////////////////////////////////////////	
	////////////////////////////////////////////
	$scope.zeroNodes=false;
	$scope.nodelist=[];
	$scope.networksList=[];
	$scope.linkLinesArray=[];
	$scope.lineList=[];
	$scope.scale=5;
	$scope.previewFun= function(path){
                $(".btn-flat").addClass('disabled');
		$scope.pathToLab=path;
		$scope.nodelist=[];
		$scope.networksList=[]
		$scope.lineList=[]
		$scope.scale=5;
		//console.log(path)
		$scope.zeroNodes=false;
		///Get all nodes ///START
		$http.get('/api/labs'+path+'/nodes').then(
			function successCallback(response) {
				//console.log(response.data)
				//console.log(ObjectLength(response.data.data))
				if (ObjectLength(response.data.data) === 0) {$scope.zeroNodes=true; return;}
				$scope.nodelist=response.data.data;
				$scope.schemecontrol($scope.scale)
				//console.log($scope.nodelist)
				//console.log(ObjectLength($scope.nodelist))
			}, 
			function errorCallback(response) {
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");}	
		).finally( function(){
			$scope.getNetworkInfo(path)
		});
		///Get all nodes ///END
	};
	/////////////////////////////
	///Get all networks ///START
	$scope.getNetworkInfo = function(path){
		$http.get('/api/labs'+path+'/networks').then(
			function successCallback(response) {
				//console.log(response.data.data)
				$scope.networksObject=response.data.data
			}, 
			function errorCallback(response) {
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");}	
		).finally( function(){
			$scope.getTopologyInfo(path)
		});
	}
	///Get all networks ///END
	//////////////////////////////
	
	///Get all connection //START
	$scope.getTopologyInfo = function(path){
		$scope.linkLinesArray=[]
		$scope.lineList=[]
		$http.get('/api/labs'+path+'/topology').then(
			function successCallback(response) {
				//console.log(response.data.data)
				$scope.topologyObject=response.data.data;
			}, 
			function errorCallback(response) {
				console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");}	
		).finally(function(){
			if ($scope.topologyObject.length === 0 ) {
				$http({
                                method: 'DELETE',
                                url: '/api/labs/close'})
                                .then(
                                        function successCallback(response) {
                                                console.log(response)
                                        },
                                        function errorCallback(response) {
                                                console.log(response)
                                        }
                                );
				$(".btn-flat.disabled").removeClass('disabled');
				return;
			}
			var lineCounter=0;
			for (i = 0; i < $scope.topologyObject.length; i++) { 
				$scope.lineList[lineCounter]=[]
				console.log($scope.topologyObject[i].destination)
				console.log($scope.topologyObject[i].source)
				if ($scope.topologyObject[i].destination.includes("network")){
					var netNum = $scope.topologyObject[i].destination.replace("network", '')
					if ($scope.networksList.indexOf(netNum) == -1)  $scope.networksList.push(netNum)
					$scope.lineList[lineCounter]['x1']=(parseFloat($scope.networksObject[netNum].left)+50)/$scope.scale*2
					$scope.lineList[lineCounter]['y1']=(parseFloat($scope.networksObject[netNum].top)+30)/$scope.scale*2
					
				}
				if ($scope.topologyObject[i].destination.includes("node")){
					var nodeNum = $scope.topologyObject[i].destination.replace("node", '')
					console.log(nodeNum)
					$scope.lineList[lineCounter]['x1']=(parseFloat($scope.nodelist[parseInt(nodeNum)].left)+50)/$scope.scale*2
					$scope.lineList[lineCounter]['y1']=(parseFloat($scope.nodelist[parseInt(nodeNum)].top)+30)/$scope.scale*2
				}
				if ($scope.topologyObject[i].source.includes("network")){
					var netNum = $scope.topologyObject[i].source.replace("network", '')
					$scope.lineList[lineCounter]['x2']=(parseFloat($scope.networksObject[netNum].left)+50)/$scope.scale*2
					$scope.lineList[lineCounter]['y2']=(parseFloat($scope.networksObject[netNum].top)+30)/$scope.scale*2
					
				}
				if ($scope.topologyObject[i].source.includes("node")){
					var nodeNum = $scope.topologyObject[i].source.replace("node", '')
					$scope.lineList[lineCounter]['y2']=(parseFloat($scope.nodelist[parseInt(nodeNum)].top)+20)/$scope.scale*2
					$scope.lineList[lineCounter]['x2']=(parseFloat($scope.nodelist[parseInt(nodeNum)].left)+40)/$scope.scale*2
					console.log(nodeNum)
				}
				lineCounter++
                                $(".btn-flat.disabled").removeClass('disabled');
			}
			console.log($scope.lineList)
			console.log($scope.networksList)
			console.log($scope.linkLinesAttr($scope.lineList[0].x1,$scope.lineList[0].y1,$scope.lineList[0].x2,$scope.lineList[0].y2, $scope.scale)[0])
			
			$http({
				method: 'DELETE',
				url: '/api/labs/close'})
				.then(
					function successCallback(response) {
						console.log(response)
					}, 
					function errorCallback(response) {
						console.log(response)
					}
				);
		});
	}
	///Get all connection //END
	////////////////////////////
	///Set scale //START
	$scope.schemecontrol = function(scale){
		$scope.scale=scale;
		$scope.getTopologyInfo($scope.pathToLab)
	}
	///Set scale //END
	////////////////////
	//Line calculator //START
	$scope.linkLinesAttr = function (x1,y1, x2,y2,scale){
		
		var length = Math.sqrt((x1-x2)*(x1-x2) + (y1-y2)*(y1-y2));
		var angle  = Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI;
		var transform = 'rotate('+angle+'deg)';
		return [length,transform]
	}
	//$scope.linkLines = function (x1,y1, x2,y2){
	//	var length = Math.sqrt((x1-x2)*(x1-x2) + (y1-y2)*(y1-y2));
	//	var angle  = Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI;
	//	var transform = 'rotate('+angle+'deg)';
	//	$('div#topologyPreview').append('<div class="line" style="position:absolute;transform: '+transform+';width: '+length
	//	+'px; top: '+y1+'px; left: '+x1+'px;"></div>');
	//}
	//Line calculator //END	


	// Stop All Nodes //START
	//$app -> delete('/api/status', function() use ($app, $db) {
	$scope.stopAll = function() {
		$http({
			method: 'DELETE',
			url: '/api/status'})
			.then(
				function successCallback(response) {
					console.log(response)
				},
				function errorCallback(response) {
					console.log(response)
				}
			);
	}
	// Stop All Nodes //STOP
};

function ObjectLength( object ) {
    var length = 0;
    for( var key in object ) {
        if( object.hasOwnProperty(key) ) {
            ++length;
        }
    }
    return length;
	/////////////////////////////////////////////	
	////////////////////////////////////////////
	///////PREVIEW FUNCTIONS////////////////END
	/////////////////////////////////////////////	
	////////////////////////////////////////////	
		
		
		
		
		
}	
