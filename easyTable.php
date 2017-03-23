<?php

/*********************************************************************
* FPDF easyTable                                                       *
*                                                                    *
* Version: 1                                                         *
* Date:    17-03-2017                                                *
* Author:  Dan Machado                                               *
* Require  FPDF v1.81                                                *
**********************************************************************/
  
 
 class easyTable{
    const LP=0.4;
    const XPadding=0.5;
    const YPadding=1;
    const IMGPadding=0.5;
    static private $table_counter=false;
    static private $style=array('width'=>false, 'border'=>false, 'border-color'=>false,
    'align'=>'', 'valign'=>'', 'bgcolor'=>false, 'split-row'=>false, 'l-margin'=>false,
    'font-family'=>false, 'font-style'=>false,'font-size'=>false, 'font-color'=>false,
    'paddingX'=>false, 'paddingY'=>false);
    private $pdf_obj;
    private $document_style;
    private $table_style;
    private $col_num;
    private $col_width;
    private $baseX;
    private $row_style_def;
    private $row_style;
    private $row_heights;
    private $row_data;
    private $rows;
    private $total_rowspan;
    private $col_counter;
    private $grid;
    private $blocks;
    private $overflow;
    private $header_row;
    private function get_available(){
       static $k=0;
       $t=count($this->grid);
       if($t>0){
          sort($this->grid);
          if($this->grid[$t-1]!=$t-1){
             for($i=$k; $i<$t; $i++){
                if($i!=$this->grid[$i]){
                   $t=$i;
                   $k=$i;
                   break;
               }
            }
         }
      }
      else{
         $k=0;
      }
      return $t;
   }
   private function is_rgb($str){
      $a=true;
      $tmp=explode(',', trim($str, ','));
      foreach($tmp as $color){
         if(!is_numeric($color) || $color<0 || $color>255){
            $a=false;
            break;
         }
      }
      return $a;
   }
   private function is_hex($str){
      $a=true;
      $n=strlen($str);
      if($n==7){
         for($i=0; $i<$n; $i++){
            if(preg_match("/[A-Fa-f0-9#]/", $str[$i])==0){
               $a=false;
               break;
            }
         }
      }
      else{
         $a=false;
      }
      return $a;
   }
   private function hextodec($str){
      $result=array();
      $str=substr($str,1);
      $str=strtoupper($str);
      $hex=array('0'=>0,'1'=>1,'2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,
      'A'=>10,'B'=>11,'C'=>12,'D'=>13,'E'=>14,'F'=>15);
      for($i=0; $i<3; $i++){
         if(isset($hex[$str[2*$i]]) && isset($hex[$str[2*$i+1]])){
            $result[$i]=$hex[$str[2*$i]]*16+$hex[$str[2*$i+1]];
         }
      }
      return $result;
   }
   private function set_color($str){
      $result=array();
      if($this->is_hex($str)){
         $result=$this->hextodec($str);
      }
      elseif($this->is_rgb($str)){
         $result=explode(',', trim($str, ','));
         for($i=0; $i<3; $i++){
            if(!isset($result[$i])){
               $result[$i]=0;
            }
         }
      }
      return $result;
   }
   private function getColor($str){
      $result=array(null, null, null);
      $i=0;
      $tmp=explode(' ', $str);
      foreach($tmp as $c){
         if(is_numeric($c)){
            $result[$i]=$c*256;
            $i++;
         }
      }
      return $result;
   }
   private function resetColor($array, $p='F'){
      if($p=='T'){
         $this->pdf_obj->SetTextColor($array[0],$array[1],$array[2]);
      }
      elseif($p=='D'){
         $this->pdf_obj->SetDrawColor($array[0],$array[1], $array[2]);
      }
      else{
         $this->pdf_obj->SetFillColor($array[0],$array[1],$array[2]);
      }
   }
   private function get_style($str, $c){
      $result=self::$style;
      if($c=='C'){
         $result['colspan']=0;
         $result['rowspan']=0;
         $result['img']=false;
      }
      if($c=='C' || $c=='R'){
         unset($result['width']);
         unset($result['border-color']);
         unset($result['split-row']);
         unset($result['l-margin']);
      }
      if($c=='R' || $c=='T'){
         if($c=='R'){
            $result['c-align']=array_pad(array(), $this->col_num, 'L');
         }
         else{
            $result['c-align']=array();
         }
      }
      if($c=='R'){
         $result['min-height']=false;
      }
      $tmp=explode(';', $str);
      foreach($tmp as $x){
         if($x && strpos($x,':')>0){
            $r=explode(':',$x);
            $r[0]=trim($r[0]);
            $r[1]=trim($r[1]);
            if(isset($result[$r[0]])){
               $result[$r[0]]=$r[1];
            }
         }
      }
      return $result;
   }
   
   private function set_style($str, $c, $pos=''){
      $sty=$this->get_style($str, $c);
      if($c=='T'){
         if(is_numeric($sty['width'])){
            $sty['width']=min(abs($sty['width']),$this->document_style['document_width']);
            if($sty['width']==0){
               $sty['width']=$this->document_style['document_width'];
            }
         }
         else{
            $x=strpos($sty['width'], '%');
            if($x!=false){
               $x=min(abs(substr($sty['width'], 0, $x)), 100);
               if($x){
                  $sty['width']=$x*$this->document_style['document_width']/100.0;
               }
               else{
                  $sty['width']=$this->document_style['document_width'];
               }
            }
            else{
               $sty['width']=$this->document_style['document_width'];
            }
         }
         if(!is_numeric($sty['l-margin'])){
            $sty['l-margin']=0;
         }
         else{
            $sty['l-margin']=abs($sty['l-margin']);
         }
         if($sty['border-color']!==false && ($this->is_hex($sty['border-color']) || $this->is_rgb($sty['border-color']))){
            $sty['border-color']=$this->set_color($sty['border-color']);
         }
         if($sty['split-row']!=false){
            $sty['split-row']=true;
         }
      }
      if($c=='R'){
         if(!is_numeric($sty['min-height']) || $sty['min-height']<0){
            $sty['min-height']=0;
         }
      }
      if(!is_numeric($sty['paddingX'])){
         if($c=='C'){
            $sty['paddingX']=$this->row_style['paddingX'];
         }
         elseif($c=='R'){
            $sty['paddingX']=$this->table_style['paddingX'];
         }
         else{
            $sty['paddingX']=self::XPadding;
         }
      }
      $sty['paddingX']=abs($sty['paddingX']);
      if(!is_numeric($sty['paddingY'])){
         if($c=='C'){
            $sty['paddingY']=$this->row_style['paddingY'];
         }
         elseif($c=='R'){
            $sty['paddingY']=$this->table_style['paddingY'];
         }
         else{
            $sty['paddingY']=self::YPadding;
         }
      }
      $sty['paddingY']=abs($sty['paddingY']);
      $border=array('T'=>0, 'R'=>0, 'B'=>0, 'L'=>0);
      if(is_numeric($sty['border']) && $sty['border']==1){
         $border=array('T'=>1, 'R'=>1, 'B'=>1, 'L'=>1);
      }
      else{
         if(strpos($sty['border'], 'T')!==false){
            $border['T']=1;
         }
         if(strpos($sty['border'], 'R')!==false){
            $border['R']=1;
         }
         if(strpos($sty['border'], 'B')!==false){
            $border['B']=1;
         }
         if(strpos($sty['border'], 'L')!==false){
            $border['L']=1;
         }
      }
      if($sty['border']===false && ($c=='C' || $c=='R')){
         if($c=='C'){
            $sty['border']=$this->row_style['border'];
         }
         else{
            $sty['border']=$this->table_style['border'];
         }
      }
      else{
         $sty['border']=$border;
      }
      if($sty['bgcolor']===false || !($this->is_hex($sty['bgcolor']) || $this->is_rgb($sty['bgcolor']))){
         if($c=='C'){
            $sty['bgcolor']=$this->row_style['bgcolor'];
         }
         elseif($c=='R'){
            $sty['bgcolor']=$this->table_style['bgcolor'];
         }
      }
      else{
         $sty['bgcolor']=$this->set_color($sty['bgcolor']);
      }
      if($sty['font-color']===false || !($this->is_hex($sty['font-color']) || $this->is_rgb($sty['font-color']))){
         if($c=='C'){
            $sty['font-color']=$this->row_style['font-color'];
         }
         elseif($c=='R'){
            $sty['font-color']=$this->table_style['font-color'];
         }
         else{
            $sty['font-color']=$this->document_style['font-color'];
         }
      }
      else{
         $sty['font-color']=$this->set_color($sty['font-color']);
      }
      $font_settings=array('font-family', 'font-style', 'font-size');
      foreach($font_settings as $setting){
         if($sty[$setting]===false){
            if($c=='C'){
               $sty[$setting]=$this->row_style[$setting];
            }
            elseif($c=='R'){
               $sty[$setting]=$this->table_style[$setting];
            }
            else{
               $sty[$setting]=$this->document_style[$setting];
            }
         }
      }
      if($c=='C'){
         if($sty['img']){
            $tmp=explode(',', $sty['img']);
            $sty['img']=array('path'=>'', 'h'=>0, 'w'=>0);
            $img=@ getimagesize($tmp[0]);
            if($img){
               $sty['img']['path']=$tmp[0];
               for($i=1; $i<3; $i++){
                  if(isset($tmp[$i])){
                     $tmp[$i]=trim(strtolower($tmp[$i]));
                     if($tmp[$i][0]=='w' || $tmp[$i][0]=='h'){
                        $t=substr($tmp[$i],1);
                        if(is_numeric($t)){
                           $sty['img'][$tmp[$i][0]]=abs($t);
                        }
                     }
                  }
               }
               $ration=$img[0]/$img[1];
               if($sty['img']['w']+$sty['img']['h']==0){
                  $sty['img']['w']=$img[0];
                  $sty['img']['h']=$img[1];
               }
               elseif($sty['img']['w']==0){
                  $sty['img']['w']=$sty['img']['h']*$ration;
               }
               elseif($sty['img']['h']==0){
                  $sty['img']['h']=$sty['img']['w']/$ration;
               }
            }
            else{
               error_log('failed to open stream: file ' . $tmp[0] .' does not exist');
            }
         }
         if(is_numeric($sty['colspan']) && $sty['colspan']>0){
            $sty['colspan']--;
         }
         else{
            $sty['colspan']=0;
         }
         if(is_numeric($sty['rowspan']) && $sty['rowspan']>0){
            $sty['rowspan']--;
         }
         else{
            $sty['rowspan']=0;
         }
         if($sty['valign']==false && ($sty['rowspan']>0 || $sty['img']!==false)){
            $sty['valign']='M';
         }
         if($sty['align']==false && $sty['img']!==false){
            $sty['align']='C';
         }
      }
      if($c=='T' || $c=='R'){
         $tmp=explode('{',$sty['align']);
         if($c=='T'){
            $sty['align']=trim($tmp[0]);
         }
         if(isset($tmp[1])){
            $tmp[1]=trim($tmp[1], '}');
            if(strlen($tmp[1])){
               for($i=0; $i<strlen($tmp[1]); $i++){
                  if(preg_match("/[LCRJ]/", $tmp[1][$i])!=0){
                     $sty['c-align'][$i]=$tmp[1][$i];
                  }
                  else{
                     $sty['c-align'][$i]='L';
                  }
               }
            }
            if($c=='R'){
               $sty['align']='L';
               $sty['c-align']=array_slice($sty['c-align'],0,$this->col_num);
            }
         }
      }
      if($sty['align']!='L' && $sty['align']!='C' && $sty['align']!='R' && $sty['align']!='J'){
         if($c=='C'){
            $sty['align']=$this->row_style['c-align'][$pos];
         }
         elseif($c=='R'){
            $sty['align']='L';
            $sty['c-align']=$this->table_style['c-align'];
         }
         else{
            $sty['align']='C';
         }
      }
      elseif($c=='T' && $sty['align']=='J'){
         $sty['align']='C';
      }
      if($sty['valign']!='T' && $sty['valign']!='M' && $sty['valign']!='B'){
         if($c=='C'){
            $sty['valign']=$this->row_style['valign'];
         }
         elseif($c=='R'){
            $sty['valign']=$this->table_style['valign'];
         }
         else{
            $sty['valign']='T';
         }
      }
      return $sty;
   }
   private function row_content_loop($counter, $f){
      $t=0;
      if($counter>0){
         $t=$this->rows[$counter-1];
      }
      for($index=$t; $index<$this->rows[$counter]; $index++){
         $f($index);
      }
   }
   private function mk_border($i, $y, $split){
      $w=$this->row_data[$i][2];
      $h=$this->row_data[$i][5];
      if($split){
         $h=$this->pdf_obj->PageBreak()-$y;
      }
      if($this->row_data[$i][1]['border']['T']){
         $this->pdf_obj->Line($this->row_data[$i][6], $y, $this->row_data[$i][6]+$w, $y);
      }
      if($this->row_data[$i][1]['border']['R']){
         $this->pdf_obj->Line($this->row_data[$i][6]+$w, $y, $this->row_data[$i][6]+$w, $y+$h);
      }
      if($split==0 && $this->row_data[$i][1]['border']['B']){
         $this->pdf_obj->Line($this->row_data[$i][6], $y+$h, $this->row_data[$i][6]+$w, $y+$h);
      }
      if($this->row_data[$i][1]['border']['L']){
         $this->pdf_obj->Line($this->row_data[$i][6], $y, $this->row_data[$i][6], $y+$h);
      }
      if($split){
         $this->row_data[$i][1]['border']['T']=0;
      }
   }
   private function print_text($i, $y, $split){
      $padding=$this->row_data[$i][1]['padding-y'];
      $k=$padding;
      if($this->row_data[$i][1]['img']!==false){
         if($this->row_data[$i][1]['valign']=='B'){
            $k+=$this->row_data[$i][1]['img']['h']+self::IMGPadding;
         }
      }
      $l=0;
      if(count($this->row_data[$i][0])){
         $x=$this->row_data[$i][6]+$this->row_data[$i][1]['paddingX'];
         $xpadding=2*$this->row_data[$i][1]['paddingX'];
         $l=count($this->row_data[$i][0])* self::LP*$this->row_data[$i][1]['font-size'];
         $this->pdf_obj->SetXY($x, $y+$k);
         $this->resetColor($this->row_data[$i][1]['font-color'], 'T');
         $this->pdf_obj->SetFont($this->row_data[$i][1]['font-family'], $this->row_data[$i][1]['font-style'], $this->row_data[$i][1]['font-size']);
         $this->pdf_obj->CellBlock($this->row_data[$i][2]-$xpadding, self::LP*$this->row_data[$i][1]['font-size'], $this->row_data[$i][0], $this->row_data[$i][1]['align']);
         $this->pdf_obj->SetFont($this->document_style['font-family'], $this->document_style['font-style'], $this->document_style['font-size']);
         $this->resetColor($this->document_style['font-color'], 'T');
      }
      if($this->row_data[$i][1]['img']!==false ){
         $x=$this->row_data[$i][6];
         $k=$padding;
         if($this->row_data[$i][1]['valign']!='B'){
            $k+=$l+self::IMGPadding;
         }
         if($this->imgbreak($i, $y)==0 && $y+$k+$this->row_data[$i][1]['img']['h']<$this->pdf_obj->PageBreak()){
            $x+=$this->row_data[$i][1]['paddingX'];
            if($this->row_data[$i][2]>$this->row_data[$i][1]['img']['w']){
               if($this->row_data[$i][1]['align']=='C'){
                  $x-=$this->row_data[$i][1]['paddingX'];
                  $x+=($this->row_data[$i][2]-$this->row_data[$i][1]['img']['w'])/2;
               }
               elseif($this->row_data[$i][1]['align']=='R'){
                  $x+=$this->row_data[$i][2]-$this->row_data[$i][1]['img']['w'];
                  $x-=2*$this->row_data[$i][1]['paddingX'];
               }
            }
            $this->pdf_obj->Image($this->row_data[$i][1]['img']['path'], $x, $y+$k, $this->row_data[$i][1]['img']['w'], $this->row_data[$i][1]['img']['h']);
         }
      }
   }
   
   private function mk_bg($i, $T, $split){
      $h=$this->row_data[$i][5];
      if($split){
         $h=$this->pdf_obj->PageBreak()-$T;
      }
      if($this->row_data[$i][1]['bgcolor']!=false){
         $this->resetColor($this->row_data[$i][1]['bgcolor']);
         $this->pdf_obj->Rect($this->row_data[$i][6], $T, $this->row_data[$i][2], $h, 'F');
         $this->resetColor($this->document_style['bgcolor']);
      }
   }
   private function printing_loop(){
      $this->pdf_obj->SetX($this->baseX);
      $y=$this->pdf_obj->GetY();
      $tmp=array();
      $rw=array();
      $ztmp=array();
      $total_cells=count($this->row_data);
      while(count($tmp)!=$total_cells){
         $a=count($this->rows);
         $h=0;
         $y=$this->pdf_obj->GetY();
         for($j=0; $j<count($this->rows); $j++){
            $T=$y+$h;
            if($T<$this->pdf_obj->PageBreak()){
                  $this->row_content_loop($j, function($index)use($T, $tmp){
                  if(!isset($tmp[$index])){
                     $split_cell=$this->scan_for_breaks($index,$T, false);
                     $this->mk_bg($index, $T, $split_cell);
                  }
               });
               if(!isset($rw[$j])){
                  if($this->pdf_obj->PageBreak()-($T+$this->row_heights[$j])>=0){
                     $h+=$this->row_heights[$j];
                  }
                  else{
                     $a=$j+1;
                     break;
                  }
               }
            }
            else{
               $a=$j+1;
               break;
            }
         }
         $h=0;
         for($j=0; $j<$a; $j++){
            $T=$y+$h;
            if($T<$this->pdf_obj->PageBreak()){
                  $this->row_content_loop($j, function($index)use($T, &$tmp, &$ztmp){
                  if(!isset($tmp[$index])){
                     $split_cell=$this->scan_for_breaks($index,$T);
                     $this->mk_border($index, $T, $split_cell);
                     $this->print_text($index, $T, $split_cell);
                     if($split_cell==0){
                        $tmp[$index]=$index;
                     }
                     else{
                        $ztmp[]=$index;
                     }
                  }
               });
               if(!isset($rw[$j])){
                  $tw=$this->pdf_obj->PageBreak()-($T+$this->row_heights[$j]);
                  if($tw>=0){
                     $h+=$this->row_heights[$j];
                     $rw[$j]=$j;
                  }
                  else{
                     $this->row_heights[$j]=$this->overflow-$tw;
                  }
               }
            }
         }
         if(count($tmp)!=$total_cells){
            foreach($ztmp as $index){
               $this->row_data[$index][5]=$this->row_data[$index][7]+$this->overflow;
               if(isset($this->row_data[$index][8])){
                  $this->row_data[$index][1]['padding-y']=$this->row_data[$index][8];
                  unset($this->row_data[$index][8]);
               }
            }
            $this->overflow=0;
            $ztmp=array();
            $this->pdf_obj->addPage();
         }
         else{
            $y+=$h;
         }
      }
      return $y;
   }
   private function imgbreak($i, $y){
      $li=$y+$this->row_data[$i][1]['padding-y'];
      $ls=$this->row_data[$i][1]['img']['h'];
      if($this->row_data[$i][1]['valign']=='B'){
         $ls+=$li;
      }
      else{
         $li+=$this->row_data[$i][3]-$this->row_data[$i][1]['img']['h'];
         $ls+=$li;
      }
      $result=0;
      if($li<$this->pdf_obj->PageBreak() && $this->pdf_obj->PageBreak()<$ls){
         $result=$this->pdf_obj->PageBreak()-$li;
      }
      return $result;
   }
   private function scan_for_breaks($index, $H, $l=true){
      $print_cell=0;
      $h=($H+$this->row_data[$index][5])-$this->pdf_obj->PageBreak();
      if($h>0){
         if($l){
            $rr=$this->pdf_obj->PageBreak()-($H+$this->row_data[$index][1]['padding-y']);
            if($rr>0){
               $mx=0;
               if(count($this->row_data[$index][0]) && $this->row_data[$index][1]['img']!==false){
                  $mx=$this->imgbreak($index, $H);
                  if($mx==0){
                     if($this->row_data[$index][1]['valign']=='B'){
                        $rr-=$this->row_data[$index][1]['img']['h'];
                     }
                  }
               }
               if($mx==0 && $rr<(self::LP*$this->row_data[$index][1]['font-size'])*count($this->row_data[$index][0])){
                  $rr=$rr/(self::LP*$this->row_data[$index][1]['font-size']);
                  $n=floor($rr);
                  if($n<count($this->row_data[$index][0])){
                     $mx=(self::LP*$this->row_data[$index][1]['font-size'])*($rr-$n);
                  }
               }
               $this->overflow=max($this->overflow, $mx);
               $this->row_data[$index][8]=1;
            }
            else{
               $this->row_data[$index][8]=-1*$rr;
            }
            $this->row_data[$index][7]=$h;
         }
         $print_cell=1;
      }
      return $print_cell;
   }
   

/***********************************************************************
function __construct($fpdf_obj, $num_cols, $style='')                                        
-------------------------------------------------------------           
DESCRIPTION:                                                            
        Constructs an easyTable object
INPUT:                                                                  
        $fpdf_obj     A FPDF object constructed with the FPDF library                     
        $num_cols         Mix, the number of columns for the table (see documentation)
        $style        String, the global style for the table (see documentation)
           
***********************************************************************/

   public function __construct($fpdf_obj, $num_cols, $style=''){
      if(self::$table_counter){
         error_log('Please use the end_table method to terminate the last table');
         exit();
      }
      self::$table_counter=true;
      $this->pdf_obj=&$fpdf_obj;
      $this->document_style['bgcolor']=$this->getColor($this->pdf_obj->get_color('fill'));
      $this->document_style['font-family']=$this->pdf_obj->current_font('family');
      $this->document_style['font-style']=$this->pdf_obj->current_font('style');
      $this->document_style['font-size']=$this->pdf_obj->current_font('size');
      $this->document_style['font-color']=$this->getColor($this->pdf_obj->get_color('text'));
      $this->document_style['document_width']=$this->pdf_obj->GetPageWidth()-$this->pdf_obj->get_margin('l')-$this->pdf_obj->get_margin('r');
      $this->table_style=$this->set_style($style, 'T');
      $this->col_num=false;
      $this->col_width=array();
      if(is_int($num_cols) && $num_cols!=0){
         $this->col_num=abs($num_cols);
         $this->col_width=array_pad(array(), abs($num_cols), $this->table_style['width']/abs($num_cols));
      }
      elseif(is_string($num_cols)){
         $num_cols=trim($num_cols, '}, ');
         if($num_cols[0]!='{' && $num_cols[0]!='%'){
            error_log('Bad format for columns in Table constructor');
            exit();
         }
         $tmp=explode('{', $num_cols);
         $tp=trim($tmp[0]);
         $num_cols=explode(',', $tmp[1]);
         $w=0;
         foreach($num_cols as $c){
            if(!is_numeric($c)){
               error_log('Bad parameter format for columns in Table constructor');
               exit();
            }
            if(abs($c)){
               $w+=abs($c);
               $this->col_width[]=abs($c);
            }
            else{
               error_log('Column width can not be zero');
            }
         }
         $this->col_num=count($this->col_width);
         if($tp=='%'){
            if($w!=100){
               error_log('The sum of the percentages of the columns is not 100');
               exit();
            }
            foreach($this->col_width as $i=>$c){
               $this->col_width[$i]=$c*$this->table_style['width']/100;
            }
         }
         elseif($w!=$this->table_style['width'] && $w){
            if($w<$this->document_style['document_width']){
               $this->table_style['width']=$w;
            }
            else{
               $this->table_style['width']=$this->document_style['document_width'];
               $d=$this->table_style['width']/$w;
               for($i=0; $i<count($num_cols); $i++){
                  $this->col_width[$i]*=$d;
               }
            }
         }
      }
      if($this->col_num==false){
         error_log('Unspecified number of columns in Table constructor');
         exit();
      }
      $this->table_style['c-align']=array_pad($this->table_style['c-align'], $this->col_num, 'L');
      if($this->table_style['l-margin']){
         $this->baseX=$this->pdf_obj->get_margin('l')+min($this->table_style['l-margin'],$this->document_style['document_width']-$this->table_style['width']);
      }
      else{
         if($this->table_style['align']=='L'){
            $this->baseX=$this->pdf_obj->get_margin('l');
         }
         elseif($this->table_style['align']=='R'){
            $this->baseX=$this->pdf_obj->get_margin('l')+$this->document_style['document_width']-$this->table_style['width'];
         }
         else{
            $this->baseX=$this->pdf_obj->get_margin('l')+($this->document_style['document_width']-$this->table_style['width'])/2;
         }
      }
      $this->row_style_def=$this->set_style($style, 'R');
      $this->row_style=$this->row_style_def;
      $this->row_heights=array();
      $this->row_data=array();
      $this->rows=array();
      $this->total_rowspan=0;
      $this->col_counter=0;
      $this->grid=array();
      $this->blocks=array();
      $this->overflow=0;
      if($this->table_style['border-color']!=false){
         $this->resetColor($this->table_style['border-color'], 'D');
      }
      $this->header_row=array();
   }

/***********************************************************************
function rowStyle($style)                                        
-------------------------------------------------------------           
DESCRIPTION:                                                            
        Set the style for all the cells in the respective row.
INPUT:                                                                  
        $style        String, the style to be applied for all the cells 
                      in a particular row (see documentation)
***********************************************************************/
   
   public function rowStyle($style){
      $this->row_style=$this->set_style($style, 'R');
   }

/***********************************************************************
function easyCell($data, $style='')
------------------------------------------------------------------------
DESCRIPTION:
        Makes a cell in the table
INPUT:
        $data         Mix, the data for the respective cell
        $style        String, the style for this particular cell 
                      (see documentation)
***********************************************************************/   
   public function easyCell($data, $style=''){
      if($this->col_counter<$this->col_num){
         $this->col_counter++;
         $row_number=count($this->rows);
         $cell_index=count($this->row_data);
         $cell_pos=$this->get_available();
         $this->grid[]=$cell_pos;
         $colm=$cell_pos %$this->col_num;
         $sty=$this->set_style($style, 'C', $colm);
         if($sty['colspan'] || $sty['rowspan']){
            $a=array($cell_pos);
            for($i=0;$i<$sty['colspan']; $i++){
               $a[]=$cell_pos+$i+1;
            }
            $b=$a;
            for($j=0; $j<$sty['rowspan'];$j++){
               foreach($b as $c){
                  $a[]=$c+($j+1)*$this->col_num;
               }
            }
            for($i=1; $i<count($a); $i++){
               $this->grid[]=$a[$i];
            }
         }
         if($sty['rowspan']){
            $this->total_rowspan=max($this->total_rowspan, $sty['rowspan']);
            $this->blocks[$cell_index]=array($cell_index, $row_number, $sty['rowspan']);
         }
         $w=$this->col_width[$colm];
         $r=0;
         while($r<$sty['colspan'] && $this->col_counter<$this->col_num){
            $this->col_counter++;
            $colm++;
            $w+=$this->col_width[$colm];
            $r++;
         }
         $w-=2*$sty['paddingX'];
         $data=$this->pdf_obj->extMultiCell($sty['font-family'], $sty['font-style'], $sty['font-size'], $w, $data);
         $h=count($data) * self::LP*$sty['font-size'];
         if($sty['img']){
            if($sty['img']['w']>$w){
               $sty['img']['h']=$w*$sty['img']['h']/$sty['img']['w'];
               $sty['img']['w']=$w;
            }
            if($h){
               $h+=self::IMGPadding;
            }
            $h+=$sty['img']['h'];
         }
         $w+=2*$sty['paddingX'];
         
         $posx=$this->baseX;
         $d=$cell_pos %$this->col_num;
         for($k=0; $k<$d; $k++){
            $posx+=$this->col_width[$k];
         }
         $this->row_data[$cell_index]=array($data, $sty, $w, $h, $cell_pos, 0, $posx, 0);
         
      }
   }

/***********************************************************************
function printRow($setAsHeader=false)
------------------------------------------------------------------------
DESCRIPTION:
        Prepares the data in the row to be printed
INPUT:
        $setAsHeader    Boolean. When it is set as true, the row will be
                        set printed as table header every time the table
                        split on multiple pages.
                        Remark: the table attribute split-row should set
                        as true. 
***********************************************************************/
  
   public function printRow($setAsHeader=false){
      $this->col_counter=0;
      $row_number=count($this->rows);
      $this->rows[$row_number]=count($this->row_data);
      $mx=$this->row_style['min-height'];
         $this->row_content_loop($row_number, function($index)use(&$mx){
         if($this->row_data[$index][1]['rowspan']==0){
            $mx=max($mx, $this->row_data[$index][3]+2*$this->row_data[$index][1]['paddingY']);
         }
      });
      $this->row_heights[$row_number]=$mx;
      
      if($this->total_rowspan>0){
         $this->total_rowspan--;
      }
      else{
         $row_number=count($this->rows);
         if(count($this->blocks)>0){
            
            foreach($this->blocks as $bk_id=>$block){
               $h=0;
               for($i=$block[1]; $i<=$block[1]+$block[2]; $i++){
                  $h+=$this->row_heights[$i];
               }
               $t=$this->row_data[$block[0]][3]+2*$this->row_data[$block[0]][1]['paddingY'];
               if($h>0 && $h<$t){
                  for($i=$block[1]; $i<=$block[1]+$block[2]; $i++){
                     $this->row_heights[$i]*=$t/$h;
                  }
               }
            }
            foreach($this->blocks as $j=>$block){
               $h=0;
               for($i=$block[1]; $i<=$block[1]+$block[2]; $i++){
                  $h+=$this->row_heights[$i];
               }
               $this->row_data[$j][5]=$h;
            }
         }
         $this->overflow=0;
         $y=$this->pdf_obj->GetY();
         for($j=0; $j<$row_number; $j++){
               $this->row_content_loop($j, function($index)use($j, $y){
               if($this->row_data[$index][1]['rowspan']==0){
                  $this->row_data[$index][5]=$this->row_heights[$j];
               }
               $this->row_data[$index][1]['padding-y']=$this->row_data[$index][1]['paddingY'];
               if($this->row_data[$index][1]['valign']=='M' || ($this->row_data[$index][1]['img'] && count($this->row_data[$index][0]))){
                  $this->row_data[$index][1]['padding-y']=($this->row_data[$index][5]-$this->row_data[$index][3])/2;
               }
               elseif($this->row_data[$index][1]['valign']=='B'){
                  $this->row_data[$index][1]['padding-y']=$this->row_data[$index][5]-($this->row_data[$index][3]+$this->row_data[$index][1]['paddingY']);
               }
            });
            $y+=$this->row_heights[$j];
         }
         if($setAsHeader==true){
            if(count($this->header_row)==0 && count($this->row_heights)==1){
               $this->header_row['row_heights']=$this->row_heights;
               $this->header_row['row_data']=$this->row_data;
               $this->header_row['rows']=$this->rows;
            }
         }
         if($this->table_style['split-row']==false && $this->pdf_obj->PageBreak()<$this->pdf_obj->GetY()+$this->row_heights[0]){
            $this->pdf_obj->addPage();
            if(count($this->header_row)>0){
               $tmp=array('header_data'=>$this->header_row['row_data'], 'row_heights'=>&$this->row_heights, 'row_data'=>&$this->row_data, 'rows'=>&$this->rows);
               unset($this->row_heights, $this->row_data, $this->rows);
               $this->row_heights=&$this->header_row['row_heights'];
               $this->row_data=&$this->header_row['row_data'];
               $this->rows=&$this->header_row['rows'];
               $y=$this->printing_loop();
               $this->pdf_obj->SetXY($this->baseX, $y);
               $this->header_row['row_data']=$tmp['header_data'];
               unset($this->row_heights, $this->row_data, $this->rows);
               $this->row_heights=$tmp['row_heights'];
               $this->row_data=$tmp['row_data'];
               $this->rows=$tmp['rows'];
            }
         }
         $y=$this->printing_loop();
         $this->pdf_obj->SetXY($this->baseX, $y);
         $this->grid=array();
         $this->row_data=array();
         $this->rows=array();
         $this->row_heights=array();
         $this->blocks=array();
      }
      $this->row_style=$this->row_style_def;
   }

/***********************************************************************
function endTable($bottomMargin=2)
------------------------------------------------------------------------
DESCRIPTION:
        Unset all the data members of the easyTable object
INPUT:
        $bottomMargin   Integer, number of blank lines bellow the bottom 
                        of the table and 
***********************************************************************/   
   public function endTable($bottomMargin=2){
      self::$table_counter=false;
      if($this->table_style['border-color']!=false){
         $this->resetColor($this->document_style['bgcolor'], 'D');
      }
      $this->pdf_obj->SetX($this->pdf_obj->get_margin('l'));
      $this->pdf_obj->Ln($bottomMargin);
      unset($this->pdf_obj);
      unset($this->document_style);
      unset($this->table_style);
      unset($this->col_num);
      unset($this->col_width);
      unset($this->baseX);
      unset($this->row_style_def);
      unset($this->row_style);
      unset($this->row_heights);
      unset($this->row_data);
      unset($this->rows);
      unset($this->total_rowspan);
      unset($this->col_counter);
      unset($this->grid);
      unset($this->blocks);
      unset($this->overflow);
      unset($this->header_row);
   }
}
?>
