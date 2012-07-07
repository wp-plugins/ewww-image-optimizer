/* This script and many more are available free online at
The JavaScript Source!! http://www.javascriptsource.com
Created by: Abraham Joffe :: http://www.abrahamjoffe.com.au/ */

var startTime=new Date();

function currentTime(){
  var a=Math.floor((new Date()-startTime)/100)/10;
  if (a%1==0) a+=".0";
  document.getElementById("endTime").innerHTML=a;
}

window.onload=function(){
  clearTimeout(loopTime);
}

