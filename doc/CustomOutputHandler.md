# Custom Output Handler

User defined output handler can be set to output_handler in core\std\pool instead of the default one.  
System will finally call the output handler to process result, error, input data, etc. It will be very useful to output result in a different format for different APIs.