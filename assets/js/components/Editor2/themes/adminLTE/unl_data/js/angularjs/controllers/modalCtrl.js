function ModalCtrl($scope, $uibModal, $log) {

  //$scope.items = ['item1', 'item2', 'item3'];
  $scope.modalActions = {
		'addfile': {'path':'/themes/adminLTE/unl_data/pages/modals/addfile.html', 'controller':'AddElModalCtrl'},
		'editfile': {'path':'/themes/adminLTE/unl_data/pages/modals/editfile.html', 'controller':'EditElModalCtrl'},
		'editLab': {'path':'/themes/adminLTE/unl_data/pages/modals/editLab.html', 'controller':'EditElModalCtrl'},
		'adduser': {'path':'/themes/adminLTE/unl_data/pages/modals/adduser.html', 'controller':'AddUserModalCtrl'},
		'edituser': {'path':'/themes/adminLTE/unl_data/pages/modals/edituser.html', 'controller':'EditUserModalCtrl'},
		'moveto': {'path':'/themes/adminLTE/unl_data/pages/modals/moveto.html', 'controller':'MoveToModalCtrl'},
		'default': {'path':'/themes/adminLTE/unl_data/pages/modals/wtf.html', 'controller':'ModalInstanceCtrl'}
  };

  $scope.animationsEnabled = true;

  $scope.openModal = function (action , edituser, size) {
	$scope.edituser = (edituser === undefined) ? '' :  edituser;
	var pathToModal = (action === undefined) ? 'default' :  action;
	// console.log(size + 'aaaaaaaaaa');
    var modalInstance = $uibModal.open({
      animation: $scope.animationsEnabled,
      templateUrl: $scope.modalActions[pathToModal]['path'],
      controller: $scope.modalActions[pathToModal]['controller'],
      windowTopClass: "fade in out", 
      size: size,
      scope: $scope,
      backdrop: (size == 'megalg') ? false : true,
      resolve: {
        data: function () {
			switch(action) {
				case 'addfile':
						return {'name': $scope.newElementName, 'path': $scope.path};
						break;
				case 'editfile':
                                                $scope.labInfo.fullPathToFile = $scope.fullPathToFile;
						return {'info': $scope.labInfo, 'path': $scope.path};
						break;
				case 'editLab':
                                                $scope.labInfo.fullPathToFile = $scope.fullPathToFile;
						return {'info': $scope.labInfo, 'path': $scope.path};
						break;
				case 'adduser':
						return {'currentUserData': $scope.userdata};
						break;
				case 'edituser':
						return {'username': $scope.edituser};
						break;
				case 'moveto':
						return {'foldersArray': $scope.folderArrayToMove, 'filesArray': $scope.fileArrayToMove, 'path': $scope.path};
						break;
				default:
						return {'wtf': $scope.newElementName, 'path': $scope.path};
			}
        }
      }
    });
	switch(action) {
    case 'addfile':
		modalInstance.result.then(function (result) {
			if (result){
				$scope.newElementName='';
				$scope.newElementToggle=false;
				$scope.fileMngDraw($scope.path);
			} else {
				toastr["error"]("Server has error", "Error");
			}
		}, function () {
		//function if user just close modal
		//$log.info('Modal dismissed at: ' + new Date());
		});
		break;
	case 'editfile':
		modalInstance.result.then(function (result) {
			if (result.result){
				$scope.newElementName='';
				$scope.newElementToggle=false;
				$scope.getLabInfo(result.name)
				$scope.fileMngDraw($scope.path);
			} else {
				toastr["error"]("Server has error", "Error");
			}
		}, function () {
		//function if user just close modal
		//$log.info('Modal dismissed at: ' + new Date());
		});
		break;
        case 'editLab':
                modalInstance.result.then(function (result) {
                        if (result.result){
                                $scope.newElementName='';
                                $scope.newElementToggle=false;
                                $scope.getLabInfo(result.name)
                                $scope.fileMngDraw($scope.path);
                        } else {
                                toastr["error"]("Server has error", "Error");
                        }
                }, function () {
                //function if user just close modal
                //$log.info('Modal dismissed at: ' + new Date());
                });
                break;
	case 'adduser':
		modalInstance.result.then(function (result) {
			if (result){
				$scope.getUsersInfo()
			} else {
				toastr["error"]("Server has error", "Error");
			}
		}, function () {
		//function if user just close modal
		//$log.info('Modal dismissed at: ' + new Date());
		});
		break;
	case 'edituser':
		modalInstance.result.then(function (result) {
			if (result){
				$scope.getUsersInfo()
			} else {
				toastr["error"]("Server has error", "Error");
			}
		}, function () {
		//function if user just close modal
		//$log.info('Modal dismissed at: ' + new Date());
		});
		break;
	case 'moveto':
		modalInstance.result.then(function (result) {
			if (result){
				$scope.fileMngDraw($scope.pathBeforeMove);
			} else {
				$scope.fileMngDraw($scope.pathBeforeMove);
			}
		}, function () {
		//function if user just close modal
		//$log.info('Modal dismissed at: ' + new Date());
		console.log('here')
		//$scope.selectAll();
		$scope.allCheckedFlag=false;
		$scope.fileMngDraw($scope.pathBeforeMove);
		});
		break;
	default:
        modalInstance.result.then(function () {
		}, function () {
		$log.info('Modal dismissed at: ' + new Date());
		});
	}
  };
};

// Please note that $uibModalInstance represents a modal window (instance) dependency.
// It is not the same as the $uibModal service used above.
function ModalInstanceCtrl($scope, $uibModalInstance) {

  $scope.closeModal = function () {
    $uibModalInstance.dismiss('cancel');
  };
};
function AddElModalCtrl($scope, $uibModalInstance, data, $http) {

	$scope.blockButtons=false;
	$scope.blockButtonsClass='';
	$scope.result=false;
	$scope.author='';
	$scope.description='';
	$scope.version=1;
	$scope.body='';
	$scope.scripttimeout=300;
	$scope.labName=data.name;
	$scope.labPath=data.path;
	$scope.errorClass='';
	$scope.errorMessage='';
	$scope.restrictTest = '\\d+';
	$scope.restrictNumber = '^[a-zA-Z0-9-_ ]+$';
	
	$scope.addNewLab = function () {
		
		$scope.path = ($scope.labPath === '/') ? $scope.labPath :  $scope.labPath+'/';
		
		$scope.labName = $scope.labName.replace(/[\',#,$,\",\\,/,%,\*,\,,\.,!]/g, '')
		//$scope.labName = $scope.labName.replace(/[\s]+/g, '_');
		
		$scope.newdata = {
		'author': $scope.author,
		'description': $scope.description,
		'scripttimeout': $scope.scripttimeout,
		'version': $scope.version,
		'name': $scope.labName,
		'body': $scope.body,
		'path': $scope.path}
		
		if ($scope.labName == ''){ 
			$scope.errorMessage="Name can't be empty!";
			$scope.errorClass='has-error';
			return;
			}
			
		$scope.blockButtons=true;
		$scope.blockButtonsClass='m-progress';
		
		$http({
			method: 'POST',
			url: 'api/labs',
			data: $scope.newdata})
			.then(
			function successCallback(response) {
				$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				$scope.result=true;
				var lab_name = $scope.newdata.name+'.unl'
				$scope.$parent.legacylabopen($scope.newdata.path+lab_name)
				$uibModalInstance.close($scope.result);
			}, 
			function errorCallback(response) {
				$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				$scope.result=false;
				if (response.status == 400 && response.data.status == 'fail') {
					$scope.errorMessage="Lab with the same name found";
					$scope.errorClass='has-error';
					return;
				}
				if (response.status == 412 && response.data.status == "unauthorized"){
					console.log("Unauthorized user.")
					$uibModalInstance.dismiss('cancel');
					toastr["error"]("Unauthorized user", "Error");
				}
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?")
				//$uibModalInstance.close($scope.result);
				toastr["error"](response.data.message, "Error");
			}
		);
	}

	$scope.opacity = function(){
		$(".modal-content").toggleClass("modal-content_opacity");
	};

	$scope.closeModal = function () {
		$uibModalInstance.dismiss('cancel');
	};
};
function EditElModalCtrl($scope, $uibModalInstance, data, $http) {
	
	
	$scope.blockButtons=false;
	$scope.blockButtonsClass='';
	$scope.result=false;
	$scope.author=data.info.author;
	$scope.description=data.info.description;
	$scope.version=data.info.version;
	$scope.body=data.info.body;
	$scope.scripttimeout=data.info.scripttimeout;
	$scope.labName=data.info.name;
	$scope.oldName=data.info.name;
	$scope.labPath=data.path;
	$scope.errorClass='';
	$scope.podError=false;
	$scope.errorMessage='';
	$scope.path = ($scope.labPath === '/') ? $scope.labPath :  $scope.labPath+'/';
	//console.log(data.info)
	
	$scope.editLab = function() {
		$scope.labName = $scope.labName.replace(/[\',#,$,\",\\,/,%,\*,\,,\.,!]/g, '')
		//$scope.labName = $scope.labName.replace(/[\s]+/g, '_');
		$scope.newdata = {
		'author': $scope.author,
		'description': $scope.description,
		'body': $scope.body,
		'scripttimeout': $scope.scripttimeout,
		'version': $scope.version,
		'name': $scope.labName}
		
		if ($scope.labName == ''){ 
			$scope.errorMessage="Name can't be empty!";
			$scope.errorClass='has-error';
			return;
		}
		
		$http({
			method: 'PUT',
			url: 'api/labs'+$scope.path+$scope.oldName+'.unl',
			data: $scope.newdata})
			.then(
			function successCallback(response) {
				$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				$scope.result= {
					'result' : true,
					'name' : $scope.path+$scope.labName+'.unl'
				}
				$uibModalInstance.close($scope.result);
			}, 
			function errorCallback(response) {
				$scope.blockButtons=false;
				$scope.blockButtonsClass='';
				$scope.result=false;
				if (response.status == 400 && response.data.status == 'fail') {
					$scope.errorMessage="Lab with the same name found";
					$scope.errorClass='has-error';
					return;
				}
				if (response.status == 412 && response.data.status == "unauthorized"){
					console.log("Unauthorized user.")
					$uibModalInstance.dismiss('cancel');
					toastr["error"]("Unauthorized user", "Error");
				}
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?")
				//$uibModalInstance.close($scope.result);
				toastr["error"](response.data.message, "Error");
			}
		);
	}
	
	$scope.closeModal = function () {
		$uibModalInstance.dismiss('cancel');
	};
}

function AddUserModalCtrl($scope, $uibModalInstance, $http, data) {
	$scope.roles='';
	$scope.selectRole='';
	$scope.roleArray=[];
	$scope.username='';
	$scope.name='';
	$scope.email='';
	$scope.passwd='';
	$scope.passwdConfirm='';
	$scope.role='';
	$scope.podArray=[];
	$scope.expiration='-1';
	$scope.restrictNumber = '^[a-zA-Z0-9-_ ]+$';
	$scope.patternEmail = '[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,3}$';
	//Generate unique POD //START
	var podArrayIndex=0;
	for (var key in data.currentUserData){
		$scope.podArray[podArrayIndex]=parseInt(data.currentUserData[key].pod)
		podArrayIndex++
	}
	$scope.pod=0;
	for (i = 0; i < $scope.podArray.length+10; i++) {
		$scope.pod++;
		if ($scope.podArray.indexOf($scope.pod) == -1){
			break;
		}
	}
	//Generate unique POD //END
	$scope.pexpiration='-1';
	$scope.errorClass='';
	$scope.errorMessage='';
	$scope.result=false;

	$scope.closeModal = function () {
		$uibModalInstance.dismiss('cancel');
	};
	$http({
			method: 'GET',
	url: '/api/list/roles'})
			.then(
			function successCallback(response) {
				//console.log(response.data.data)
				$scope.roles=response.data.data;
				//$scope.roleArray = 
				$.map($scope.roles, function(value, index) {
					$scope.roleArray[value]=index;
				});
				//console.log($scope.roleArray)
			}, 
			function errorCallback(response) {
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?")
				$uibModalInstance.close($scope.result);
			}
	);
	
	$scope.addNewUser = function(){
		$scope.errorClass=''; 
		$scope.errorMessage="";
		$scope.podError = false;
	$scope.username = $scope.username.replace(/[\',#,$,@,\",\\,/,%,\*,\,,\.,(,),:,;,^,&,\[,\],|]/g, '')
		if ($scope.passwdConfirm!=$scope.passwd) {$scope.errorClass='has-error passwdConfirm'; $scope.errorMessage="Password doesn't match";}
		if ($scope.passwdConfirm=='') {$scope.errorClass='has-error passwdConfirm'; $scope.errorMessage="Password can't be empty!";}
		if ($scope.passwd=='') {$scope.errorClass='has-error passwd'; $scope.errorMessage="Password can't be empty!";}
		if ($scope.username=='') {$scope.errorClass='has-error username'; $scope.errorMessage="Username can't be empty!";}
		if ($scope.passwd=='whereismypassword?') { $scope.passwd='' ;}
		if ($scope.errorClass!=''){return;}
		
		$http.get('/api/users/').then(function(response){
			
			console.log(response.data.data)
			//Compare unique POD //START
			for (var key in response.data.data){
				console.log("pod", response.data.data[key].pod);
				if (parseInt(response.data.data[key].pod) == parseInt($scope.pod) && response.data.data[key].username != $scope.username) {
					$scope.podError=true; 
					break;
				}
			}
			//Compare unique POD //END
		
		}).then(function(response){
			
			if ($scope.podError){
				toastr["error"]("Please set unique POD value", "Error"); return;
			}
			
			$scope.newdata = {
				"username": $scope.username,
				"name": $scope.name,
				"email": $scope.email,
				"password": $scope.passwd,
				"role": $scope.roleArray[$scope.selectRole],
				"expiration": $scope.expiration,
				"pod": $scope.pod,
				//"pod": -1,
				"pexpiration": $scope.pexpiration,
			}
			
			$http({
				method: 'POST',
				url: '/api/users',
				data: $scope.newdata})
						.then(
						function successCallback(response) {
							//console.log(response)
							$scope.result=true;
							$uibModalInstance.close($scope.result);
						}, 
						function errorCallback(response) {
							console.log(response)
							console.log("Unknown Error. Why did API doesn't respond?")
							if (response.status == 412 && response.data.status == "unauthorized"){
								console.log("Unauthorized user.")
								$uibModalInstance.dismiss('cancel');
								toastr["error"]("Unauthorized user", "Error");
							}
							//$uibModalInstance.close($scope.result);
							toastr["error"](response.data.message, "Error");
						});
		});
	}
	
}

function EditUserModalCtrl($scope, $uibModalInstance, data, $http) {
	
	$scope.roles='';
	$scope.selectRole='';
	$scope.roleArray=[];
	$scope.username='';
	$scope.name='';
	$scope.email='';
	$scope.passwd='';
	$scope.passwdConfirm='';
	$scope.role='';
	$scope.expiration='-1';
	$scope.pod='1';
	$scope.pexpiration='-1';
	$scope.errorClass='';
	$scope.errorMessage='';
	$scope.podError=false;
	$scope.result=false;
	$scope.restrictNumber = '^[a-zA-Z0-9-_ ]+$';
	$scope.patternEmail = '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+[\.][a-zA-Z]{2,3}$';
	
	console.log('Start edit user '+data.username)
	$http({
			method: 'GET',
			url: '/api/list/roles'})
	.then(
			function successCallback(response) {
				//console.log(response.data.data['admin'])
				$scope.roles=response.data.data;
				//$scope.roleArray = 
				$.map($scope.roles, function(value, index) {
					$scope.roleArray[value]=index;
				});
				$scope.getuserInfo();
			}, 
			function errorCallback(response) {
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?")
			}
	);
	
	$scope.getuserInfo = function(){
		$http({
		method: 'GET',
		url: '/api/users/'+data.username})
		.then(
			function successCallback(response) {
				//console.log(response.data.data)
				$scope.userinfo=response.data.data;
				$scope.username=data.username;
				$scope.name=$scope.userinfo.name;
				$scope.email=$scope.userinfo.email;
				$scope.passwd='whereismypassword?';
				$scope.passwdConfirm='whereismypassword?';
				$scope.role=$scope.userinfo.role;
				$scope.expiration='-1';
				$scope.pod=$scope.userinfo.pod;
				$scope.pexpiration='-1';
				$scope.selectRole = $scope.roles[$scope.role]
			}, 
			function errorCallback(response) {
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?")
			}
		).finally( function() {$scope.selectRole = $scope.roles[$scope.role]});
	}
	
	$scope.editUser = function(){
		
		$scope.errorClass=''; 
		$scope.errorMessage="";
		if ($scope.passwdConfirm!=$scope.passwd) {$scope.errorClass='has-error passwdConfirm'; $scope.errorMessage="Password doesn't match";}
		if ($scope.passwdConfirm=='') {$scope.errorClass='has-error passwdConfirm'; $scope.errorMessage="Password can't be empty!";}
		if ($scope.passwd=='') {$scope.errorClass='has-error passwd'; $scope.errorMessage="Password can't be empty!";}
		if ($scope.passwd=='whereismypassword?') { $scope.passwd='' ;}
		if ($scope.errorClass!=''){return;}
		
		$http.get('/api/users/').then(function(response){
			
			console.log(response.data.data)
			//Compare unique POD //START
			for (var key in response.data.data){
				console.log(parseInt(response.data.data[key].pod))
				if (parseInt(response.data.data[key].pod) == parseInt($scope.pod) && response.data.data[key].username != $scope.username) {
					$scope.podError=true; break;
				}
			}
			//Compare unique POD //END
			console.log($scope.podError)
		}).then(function(response){
		if ($scope.podError){toastr["error"]("Please set unique POD value", "Error"); return;}
		
		$scope.newdata = {
			"username": $scope.username,
			"name": $scope.name,
			"email": $scope.email,
			"password": $scope.passwd,
			"role": $scope.roleArray[$scope.selectRole],
			"expiration": $scope.expiration,
			"pod": $scope.pod,
			"pexpiration": $scope.pexpiration,
		}
		
		$http({
		method: 'PUT',
		url: '/api/users/'+data.username,
		data: $scope.newdata})
		.then(
			function successCallback(response) {
				//console.log(response)
				console.log('End edit user '+data.username)
				$scope.result=true;
				$uibModalInstance.close($scope.result);
			}, 
			function errorCallback(response) {
				console.log(response)
				console.log("Unknown Error. Why did API doesn't respond?")
				if (response.status == 412 && response.data.status == "unauthorized"){
							console.log("Unauthorized user.")
							$uibModalInstance.dismiss('cancel');
							toastr["error"]("Unauthorized user", "Error");
				}
				$uibModalInstance.close($scope.result);
				toastr["error"](response.data.message, "Error");
			});
		});
	}
	
	
	$scope.closeModal = function () {
		$uibModalInstance.dismiss('cancel');
	};
	
}

function MoveToModalCtrl($scope, $uibModalInstance, data, $http, $location,$interval) {
	
	$scope.filedata=data.filesArray
	$scope.folderdata=data.foldersArray
	$scope.path=data.path
	$scope.pathForTest=($scope.path === '/') ? $scope.path :  $scope.path+'/';
	$scope.errorMessage="";
	$scope.folderSearchList=[];
	$scope.currentSearchPath='';
	$scope.newpath="";
	$scope.openDropdown="";
	$scope.pathDeeper=0;
	$scope.pathDeeperCheck=0;
	$scope.apiSearch=false;
	$scope.localSearch="";
	$scope.blockButtons=false;
	$scope.blockButtonsClass='';
	// $scope.inputSlash=$('#newPathInput');
	//$("#newPathInput").dropdown();
	console.log($scope.filedata)
	console.log($scope.folderdata)
	
	// $scope.inputSlash = function(){
	// 	$('#newPathInput').focus();
	// 	var inputSlash = $('#newPathInput').val();
	// 	inputSlash.val('/');
	// 	inputSlash.val(inputSlash);
	// }

	$scope.fastSearch = function(pathInput){
		$scope.errorMessage="";
		var re = /^\//;
		//console.log($scope.newpath.search(re))
		if (pathInput == "" || pathInput.search(re) == -1) {$scope.openDropdown=""; return;}
		var fullPathSplit=$scope.newpath.split('/')
		var pathSearch='';
		console.log(fullPathSplit)
		$scope.localSearch=fullPathSplit[fullPathSplit.length-1];
		console.log(fullPathSplit.length)
		if ($scope.pathDeeperCheck > fullPathSplit.length-1) $scope.pathDeeper=fullPathSplit.length-2
		$scope.pathDeeperCheck = fullPathSplit.length-1
		for (z = 0; z < (fullPathSplit.length-1); z++) {
			pathSearch+=fullPathSplit[z]+'/'
		}
			console.log(pathSearch)
		if ($scope.pathDeeper < fullPathSplit.length-1){
			$scope.localSearch='';
			$scope.apiSearch=true;
			$scope.openDropdown="open";
			$scope.pathDeeper=fullPathSplit.length-1
			console.log('API search')
			$scope.currentSearchPath=pathSearch;
			if (pathSearch != '/') pathSearch=pathSearch.replace(/\/$/, '');
			$http.get('/api/folders'+pathSearch).then(
				function successCallback(response) {
					$scope.folderSearchList=response.data.data.folders
					if  ($scope.folderSearchList.length ==  1) $scope.openDropdown="";
					console.log(response)
					$scope.apiSearch=false;
				}, 
				function errorCallback(response) {
				console.log(response)
				$scope.apiSearch=false;
				if (response.status == 412 && response.data.status == "unauthorized"){
							console.log("Unauthorized user.")
							$uibModalInstance.dismiss('cancel');
							toastr["error"]("Unauthorized user", "Error");
				}
				//console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");
				}	
			);
		} else {
			if ($scope.localSearch == '') {$scope.openDropdown=""; return;}
			$scope.openDropdown="open";
			console.log('Local Search');
			console.log($scope.localSearch)
			
		}
	}
	$scope.fastSearchFast = function(foldername){
		var fastPath=$scope.currentSearchPath+foldername+'/';
		$scope.newpath=fastPath;
		$scope.fastSearch(fastPath);
		$("#newPathInput").focus();

	}
	
	$scope.deselect = function(){
	
	}
	
	$scope.move = function(){
		$scope.openDropdown="";
		$scope.folderfound=true;
		var re = /^\/.*\/$/;
		$scope.newpath = "/" + $scope.newpath
		console.log($scope.newpath.search(re))
		$scope.errorMessage="";
		if ($scope.newpath == "") {$scope.errorMessage="New path can't be empty"; return;}
		if ($scope.newpath.search(re) == -1 && $scope.newpath != "/") {$scope.errorMessage="Unknown path format, be sure that you added '/' to the end"; return;}
		if ($scope.pathForTest == $scope.newpath) {$scope.errorMessage="Path can't be the same"; return;}
		
		for (i = 0; i < $scope.folderdata.length; i++) {
			if ($scope.pathForTest+$scope.folderdata[i]+'/' == $scope.newpath) {$scope.errorMessage="You can't select this directory"; return;}
			//console.log($scope.pathForTest+$scope.folderdata[i][0]+'/')
		}
		$http.get('/api/folders'+$scope.newpath.replace(/\/$/, '')).then(
				function successCallback(response) {
					console.log(response)
				}, 
				function errorCallback(response) {
				console.log(response)
				console.log(response.status)
				console.log(response.statusText)
				if (response.status==404 && response.statusText=='Not Found') {$scope.errorMessage="You set incorrect path. Folder no found!"; $scope.folderfound=false; return;}
				//console.log("Unknown Error. Why did API doesn't respond?"); $location.path("/login");
				}	
		).finally( function(){
			if ($scope.folderfound){
				$scope.blockButtons=true;
				$scope.blockButtonsClass='m-progress';
				var folderTester=$scope.folderdata.length
				var fileTester=$scope.filedata.length
				var stopTester=5;
				if ($scope.folderdata.length > 0)
					for (fo = 0; fo < $scope.folderdata.length; fo++) {
				///Move Folders///START
						$http({
						method: 'PUT',
						url: '/api/folders'+$scope.pathForTest+$scope.folderdata[fo],
						data: {"path":$scope.newpath+$scope.folderdata[fo]}})
							.then(
							function successCallback(response) {
								console.log(response)
								folderTester--
							}, 
							function errorCallback(response) {
								console.log(response)
								if (response.status == 412 && response.data.status == "unauthorized"){
											console.log("Unauthorized user.")
											$uibModalInstance.dismiss('cancel');
											toastr["error"]("Unauthorized user", "Error");
								}
								if (response.data.message=="Destination folder already exists (60046).") {folderTester--; toastr["error"]("Destination folder already exists", "Error");}
								console.log("Unknown Error. Why did API doesn't respond?");
								//$location.path("/login");
							}
						); 
					} 
				///Move Folders///END
				/////////////////////
				//Edit APPLY for File //START
				if ($scope.filedata.length > 0)
					for (fi = 0; fi < $scope.filedata.length; fi++) {
						var tempPathNew = ($scope.newpath  == '/') ? $scope.newpath  : $scope.newpath.replace(/\/$/, '');
						$http({
						method: 'PUT',
						url: '/api/labs'+$scope.pathForTest+$scope.filedata[fi]+ '/move',
						data: {"path": tempPathNew}})
							.then(
							function successCallback(response) {
								console.log(response)
								fileTester--
							}, 
							function errorCallback(response) {
								console.log(response)
								if (response.data.message=="Lab already exists (60016).") {fileTester--; toastr["error"]("Lab already exists", "Error");}
								if (response.status == 412 && response.data.status == "unauthorized"){
											console.log("Unauthorized user.")
											$uibModalInstance.dismiss('cancel');
											toastr["error"]("Unauthorized user", "Error");
								}
								toastr["error"](response.data.message, "Error");
								console.log("Unknown Error. Why did API doesn't respond?");
								$uibModalInstance.dismiss('cancel');
							}
						);
				//Edit APPLY for File //END
					}
				$interval(function () {
					console.log
					if ( (folderTester<=0 && fileTester<=0) || stopTester==0 ) {
						$scope.result=true;$uibModalInstance.close($scope.result); 
						$scope.blockButtons=false;
						$scope.blockButtonsClass='';
						return;}
					else stopTester--
				}, 1000);
			}
		})
		
		console.log($scope.errorMessage)
	}

	$scope.closeModal = function () {
		$uibModalInstance.dismiss('cancel');
	};
	
}

