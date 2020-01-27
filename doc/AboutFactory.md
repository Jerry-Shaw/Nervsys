# About Factory

There is a factory handler under "core/lib/stc", which provides object creation and mapping.  
 
Meanwhile, there is another factory in "ext", which just controls the same object pool by calling "core\lib\stc\factory" methods. So that, all other user classes should be always extended from "ext\factory", for the purpose of letting main factory control the whole object creation job.
