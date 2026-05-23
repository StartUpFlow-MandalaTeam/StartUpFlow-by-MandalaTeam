function validation(){
    if(document.Formfill.Username.value==""){
        document.getElementById("result").innerHTML="Enter Username*";
        return false;
    }
    else if(document.Formfill.Username.value.length<6){
        document.getElementById("result").innerHTML="At least six letter*";
        return false;
    }
    else if(document.Formfill.Email.value==""){
        document.getElementById("result").innerHTML="Enter your Email*";
        return false;
    }
    else if(document.Formfill.Password.value==""){
        document.getElementById("result").innerHTML="Enter your Password*";
        return false;
    }
    else if(document.Formfill.CPassword.value.lenght<6){
        document.getElementById("result").innerHTML="Password must be 6-digits";
        return false;
    }
    else if(document.Formfill.CPassword.value==""){
        document.getElementById("result").innerHTML="Enter Confirm Password";
        return false;
    }
    else if(document.Formfill.CPassword.value !== document.Formfill.Password.value){
        document.getElementById("result").innerHTML="Password doesn't matched";
        return false;
    }
    else if(document.Formfill.Password.value == document.Formfill.CPassword.value){
        popup.classList.add("open-slide")
        return false;
    }
}

function resetValidation(){
    if(document.Formfill.Password.value==""){
        document.getElementById("result").innerHTML="Enter new Password*";
        return false;
    }
    else if(document.Formfill.CPassword.value==""){
        document.getElementById("result").innerHTML="Enter Confirm Password";
        return false;
    }
    else if(document.Formfill.CPassword.value !== document.Formfill.Password.value){
        document.getElementById("result").innerHTML="Password doesn't matched";
        return false;
    }
    else if(document.Formfill.Password.value == document.Formfill.CPassword.value){
        popup.classList.add("open-slide")
        return false;
    }
}

var popup=document.getElementById('popup');

function CloseSlide(){
    popup.classList.remove("open-slide")
}